<?php

namespace App\Support\Presenter;

/**
 * In-house replacement for Laracasts\Presenter\Presenter.
 * See PresenterException for why this package was vendored in-house.
 *
 * Behaviour is identical to the original: the presenter wraps an entity and
 * exposes property-style access, preferring a method on the presenter when one
 * exists and otherwise falling through to the wrapped entity's property.
 */
abstract class Presenter
{
    /**
     * The wrapped entity.
     *
     * @var mixed
     */
    protected $entity;

    /**
     * @param  mixed  $entity
     */
    public function __construct($entity)
    {
        $this->entity = $entity;
    }

    /**
     * Allow for property-style retrieval.
     *
     * @param  string  $property
     * @return mixed
     */
    public function __get($property)
    {
        if (method_exists($this, $property)) {
            return $this->{$property}();
        }

        return $this->entity->{$property};
    }
}
