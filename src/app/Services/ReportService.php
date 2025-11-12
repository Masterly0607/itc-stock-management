<?php

namespace App\Services;

use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class ReportService
{
  /**
   * Stock levels by (branch, product[, unit]) with available = on_hand - reserved.
   * Works whether stock_levels has (on_hand,reserved) or (qty[,reserved]).
   */
  public function stockLevels(array $filters = []): Collection
  {
    $table = 'stock_levels';
    $hasUnit   = Schema::hasColumn($table, 'unit_id');
    $hasOnHand = Schema::hasColumn($table, 'on_hand');
    $hasQty    = Schema::hasColumn($table, 'qty');
    $hasRes    = Schema::hasColumn($table, 'reserved');

    $amountCol = $hasOnHand ? 'on_hand' : ($hasQty ? 'qty' : null);
    if (!$amountCol) {
      // No recognizable quantity columns; return empty collection
      return collect();
    }

    $group = ['stock_levels.branch_id', 'stock_levels.product_id'];
    if ($hasUnit) $group[] = 'stock_levels.unit_id';

    $select = $group;
    $selectRaw = [];
    $selectRaw[] = "SUM($amountCol) as on_hand";
    $selectRaw[] = $hasRes ? "SUM(reserved) as reserved" : "0 as reserved";

    $q = DB::table($table)
      ->select($select)
      ->selectRaw(implode(', ', $selectRaw));

    if (!empty($filters['branch_id'])) $q->where('branch_id', $filters['branch_id']);
    if (!empty($filters['product_id'])) $q->where('product_id', $filters['product_id']);

    $rows = $q->groupBy($group)->get();

    // decorate with names + available
    return $rows->map(function ($r) use ($hasUnit) {
      $branch = DB::table('branches')->where('id', $r->branch_id)->value('name');
      $product = DB::table('products')->where('id', $r->product_id)->value('name');
      $unit   = $hasUnit && isset($r->unit_id) ? DB::table('units')->where('id', $r->unit_id)->value('name') : null;

      $reserved  = (float)($r->reserved ?? 0);
      $on_hand   = (float)($r->on_hand ?? 0);
      $available = $on_hand - $reserved;

      return (object)[
        'branch_id'  => $r->branch_id,
        'branch'     => $branch,
        'product_id' => $r->product_id,
        'product'    => $product,
        'unit_id'    => $r->unit_id ?? null,
        'unit'       => $unit,
        'on_hand'    => $on_hand,
        'reserved'   => $reserved,
        'available'  => $available,
      ];
    });
  }

  /**
   * Ledger summary by movement in a date range.
   * Returns total qty per movement (SALE_OUT, TRANSFER_IN, etc.).
   */
  public function ledgerSummary(?string $from = null, ?string $to = null, ?int $branchId = null): Collection
  {
    $q = DB::table('inventory_ledger')
      ->select('movement')
      ->selectRaw('SUM(qty) as total_qty');

    if ($from) $q->where('posted_at', '>=', $from);
    if ($to)   $q->where('posted_at', '<=', $to);
    if ($branchId) $q->where('branch_id', $branchId);

    return $q->groupBy('movement')->orderBy('movement')->get();
  }

  /**
   * Low-stock report: available < threshold.
   * If products.min_stock exists, prefer that per product; else default threshold.
   */
  public function lowStock(?int $branchId = null, int $defaultThreshold = 10): Collection
  {
    $rows = $this->stockLevels(['branch_id' => $branchId]);

    $hasMinStock = Schema::hasColumn('products', 'min_stock');

    return $rows->filter(function ($r) use ($hasMinStock, $defaultThreshold) {
      $min = $defaultThreshold;
      if ($hasMinStock) {
        $pMin = DB::table('products')->where('id', $r->product_id)->value('min_stock');
        if (!is_null($pMin)) $min = (int)$pMin;
      }
      return $r->available < $min;
    })->values();
  }

  /**
   * Quick CSV export helper (headers + rows).
   */
  public function toCsv(array $headers, \Traversable $rows): string
  {
    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, $headers);
    foreach ($rows as $row) {
      $arr = is_array($row) ? $row : (array)$row;
      fputcsv($fh, $arr);
    }
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    return $csv;
  }
  public function lowStockQuery(?int $branchId = null, int $threshold = 50): Builder
  {
    // Assumes stock_levels has on_hand and (optional) reserved
    // If your schema is qty/reserved instead, replace on_hand with qty below.
    return StockLevel::query()
      ->join('branches', 'branches.id', '=', 'stock_levels.branch_id')
      ->join('products', 'products.id', '=', 'stock_levels.product_id')
      ->leftJoin('units', 'units.id', '=', 'stock_levels.unit_id')
      ->when($branchId, fn($q) => $q->where('stock_levels.branch_id', $branchId))
      ->selectRaw('
            stock_levels.id,
            branches.name  as branch,
            products.name  as product,
            COALESCE(units.name, "-") as unit,
            stock_levels.on_hand      as on_hand,
            COALESCE(stock_levels.reserved, 0) as reserved,
            (stock_levels.on_hand - COALESCE(stock_levels.reserved, 0)) as available,
            ? as threshold
        ', [$threshold])
      ->whereRaw('(stock_levels.on_hand - COALESCE(stock_levels.reserved, 0)) < ?', [$threshold])
      ->orderBy('branches.name')
      ->orderBy('products.name');
  }

  public function lowStockCsv(?int $branchId = null, int $threshold = 50): string
  {
    $rows = $this->lowStockQuery($branchId, $threshold)->get();

    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, ['Branch', 'Product', 'Unit', 'On Hand', 'Reserved', 'Available', 'Threshold']);
    foreach ($rows as $r) {
      fputcsv($fh, [
        $r->branch,
        $r->product,
        $r->unit,
        $r->on_hand,
        $r->reserved,
        $r->available,
        $threshold,
      ]);
    }
    rewind($fh);
    return stream_get_contents($fh);
  }
  public function auditLogQuery(?string $from = null, ?string $to = null): \Illuminate\Database\Eloquent\Builder
  {
    // Build an Eloquent builder that returns the columns your table expects:
    // at, user, action, ref, meta
    return \App\Models\AuditLog::query()
      ->from('audit_logs as a')
      ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
      ->selectRaw('
            a.id,
            a.created_at as at,
            COALESCE(u.name, u.email, "System") as user,
            a.action,
            CONCAT(COALESCE(a.entity_type, ""), "#", COALESCE(a.entity_id, "")) as ref,
            a.payload as meta
        ')
      ->when($from, fn($q) => $q->whereDate('a.created_at', '>=', $from))
      ->when($to,   fn($q) => $q->whereDate('a.created_at', '<=', $to))
      ->orderByDesc('a.created_at');
  }

  public function auditCsv(?string $from = null, ?string $to = null): string
  {
    $rows = $this->auditLogQuery($from, $to)->get();

    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, ['Time', 'User', 'Action', 'Ref', 'Details']);

    foreach ($rows as $r) {
      // $r->meta is the alias of payload
      $meta = is_array($r->meta) ? json_encode($r->meta) : (string) $r->meta;
      fputcsv($fh, [
        (string) $r->at,
        (string) $r->user,
        (string) $r->action,
        (string) $r->ref,
        $meta,
      ]);
    }

    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    return $csv;
  }
}
