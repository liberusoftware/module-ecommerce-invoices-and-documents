<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Models;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Every relation restates its parent's tenant, on top of the foreign key.
 *
 * Guarded, because `withCount()` and `whereHas()` build the relation from a
 * fresh instance whose `tenant_id` is null: unguarded, that becomes
 * `where('tenant_id', '')` and every count reports zero. Both directions are
 * asserted in CustodyTest.
 */
trait RestatesTenant
{
    /**
     * @template TRelation of Relation
     *
     * @param  TRelation  $relation
     * @return TRelation
     */
    protected function scopedToTenant(Relation $relation): Relation
    {
        $tenant = $this->getAttribute('tenant_id');

        if (is_string($tenant) && $tenant !== '') {
            $relation->getQuery()->where('tenant_id', $tenant);
        }

        return $relation;
    }
}
