<?php

namespace Tests\Feature\Ledger;

use App\Models\StockLevel;
use App\Services\LedgerWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AtomicTransactionTest extends TestCase
{
  use RefreshDatabase;

  public function test_keeps_all_or_nothing_with_transactions(): void
  {
    $b = $this->makeBranch('HQ', 'HQ', 'HQ3');
    $p = $this->makeProduct('Lotion');

    StockLevel::updateOrCreate(
      ['branch_id' => $b->id, 'product_id' => $p->id],
      ['qty' => 50]
    );

    $svc = app(LedgerWriter::class);

    try {
      DB::transaction(function () use ($svc, $p, $b) {
        $svc->postOut([
          'product_id'  => $p->id,
          'branch_id'   => $b->id,
          'qty'         => 5,
          'source_type' => 'tests',
          'source_id'   => 999,
          'source_line' => 1,
        ]);
        throw new \Exception('simulate crash');
      });
    } catch (\Throwable $e) {
      // swallow
    }

    $level = StockLevel::where([
      'branch_id'  => $b->id,
      'product_id' => $p->id,
    ])->firstOrFail();

    $this->assertSame(50.0, (float) $level->qty);
  }
}
