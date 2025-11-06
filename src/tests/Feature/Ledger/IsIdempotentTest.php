<?php

namespace Tests\Feature\Ledger;

use App\Models\StockLevel;
use App\Services\LedgerWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IsIdempotentTest extends TestCase
{
  use RefreshDatabase;

  public function test_prevents_duplicate_posting_for_same_source_keys(): void
  {
    $b = $this->makeBranch('HQ', 'HQ', 'HQ2');
    $p = $this->makeProduct('Soap');

    StockLevel::updateOrCreate(
      ['branch_id' => $b->id, 'product_id' => $p->id],
      ['qty' => 100]
    );

    $svc = app(LedgerWriter::class);

    $payload = [
      'product_id'  => $p->id,
      'branch_id'   => $b->id,
      'qty'         => 10,
      'movement'    => 'OUT',
      'source_type' => 'tests',
      'source_id'   => 77,
      'source_line' => 1,
    ];

    $svc->post($payload);

    $this->expectException(ValidationException::class);
    $svc->post($payload);
  }
}
