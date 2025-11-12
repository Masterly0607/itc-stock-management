<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
  public function stockLevels(Request $request)
  {
    $qtyCol      = Schema::hasColumn('stock_levels', 'on_hand') ? 'on_hand' : 'qty';
    $reservedCol = Schema::hasColumn('stock_levels', 'reserved') ? 'reserved' : null;

    $q = DB::table('stock_levels as sl')
      ->join('products as p', 'p.id', '=', 'sl.product_id')
      ->join('branches as b', 'b.id', '=', 'sl.branch_id')
      ->select('b.name as branch', 'p.name as product')
      ->selectRaw("sl.$qtyCol as on_hand");

    if ($reservedCol) {
      $q->addSelect(DB::raw("sl.$reservedCol as reserved"));
    } else {
      $q->addSelect(DB::raw('0 as reserved'));
    }

    $q->orderBy('b.name')->orderBy('p.name');

    if ($request->query('export') === 'csv') {
      $rows = $q->get();

      $h = fopen('php://temp', 'r+');
      fputcsv($h, ['Branch', 'Product', 'On hand', 'Reserved']);
      foreach ($rows as $r) {
        fputcsv($h, [$r->branch, $r->product, (float) $r->on_hand, (float) $r->reserved]);
      }
      rewind($h);
      $csv = stream_get_contents($h);
      fclose($h);

      return response($csv, 200, [
        'Content-Type'        => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="stock-levels.csv"',
      ]);
    }

    return view('reports.stock-levels', ['rows' => $q->get()]);
  }
}
