<?php

namespace App\Models\Concerns;

use App\Models\Store;
use Illuminate\Database\Eloquent\Model;

/**
 * For rows owned by a parent row (variant → product, PO item → PO, product → store):
 * organization_id (and store_id when the table has one) are copied from the parent on
 * every save, so they can't be forged. Use together with BelongsToOrganization, whose
 * guard then rejects a parent from another organization.
 */
trait InheritsTenant
{
    /** @return array{0: class-string<Model>, 1: string} parent model class and foreign key */
    abstract protected static function tenantParent(): array;

    public static function bootInheritsTenant(): void
    {
        static::saving(function (Model $model) {
            [$class, $foreignKey] = static::tenantParent();
            $parent = $class::withoutGlobalScopes()->findOrFail($model->getAttribute($foreignKey));

            $model->setAttribute('organization_id', $parent->getAttribute('organization_id'));

            if (static::$inheritsStore ?? false) {
                $model->setAttribute('store_id', $parent instanceof Store ? $parent->getKey() : $parent->getAttribute('store_id'));
            }
        });
    }
}
