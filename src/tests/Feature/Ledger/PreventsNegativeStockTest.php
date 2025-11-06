<?php

namespace Tests\Feature\Ledger;

use App\Models\StockLevel;
use App\Services\LedgerWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PreventsNegativeStockTest extends TestCase
{
  use RefreshDatabase;

  public function test_blocks_negative_stock_on_out(): void
  {
    $b = $this->makeBranch('HQ', 'HQ', 'HQ1');
    $p = $this->makeProduct('Shampoo');

    StockLevel::updateOrCreate(
      ['branch_id' => $b->id, 'product_id' => $p->id],
      ['qty' => 5]
    );

    $svc = app(LedgerWriter::class);

    $this->expectException(ValidationException::class);

    $svc->postOut([
      'product_id'  => $p->id,
      'branch_id'   => $b->id,
      'qty'         => 10,
      'source_type' => 'tests',
      'source_id'   => 1,
      'source_line' => 1,
    ]);
  }
}
