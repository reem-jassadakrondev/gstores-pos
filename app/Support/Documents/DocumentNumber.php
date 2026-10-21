<?php

namespace App\Support\Documents;

use App\Models\DocumentSequence;
use App\Models\Store;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free running numbers per store, document type and month: PO-S01-2610-0001.
 * The sequence row is locked, so concurrent requests never get the same number.
 * (POS receipts use per-terminal numbering in phase 3, because terminals work offline.)
 */
class DocumentNumber
{
    public function next(Store $store, string $type, ?CarbonInterface $date = null): string
    {
        $period = ($date ?? now())->timezone($store->timezone ?: 'Asia/Bangkok')->format('ym');

        $number = DB::transaction(function () use ($store, $type, $period) {
            $sequence = $this->lockedSequence($store, $type, $period);
            $number = $sequence->next_no;
            $sequence->forceFill(['next_no' => $number + 1])->save();

            return $number;
        });

        return sprintf('%s-%s-%s-%04d', $type, $store->code, $period, $number);
    }

    private function lockedSequence(Store $store, string $type, string $period): DocumentSequence
    {
        $query = fn () => DocumentSequence::withoutGlobalScopes()
            ->where('store_id', $store->getKey())
            ->where('doc_type', $type)
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        if ($sequence = $query()) {
            return $sequence;
        }

        try {
            DocumentSequence::create(['store_id' => $store->getKey(), 'doc_type' => $type, 'period' => $period, 'next_no' => 1]);
        } catch (QueryException) {
            // Another request created it first; fall through and lock theirs.
        }

        return $query();
    }
}
