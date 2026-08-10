<?php

namespace App\Support\Presenter;

/**
 * In-house replacement for Laracasts\Presenter\PresentableTrait.
 * See PresenterException for why this package was vendored in-house.
 *
 * Behaviour is identical to the original, with one deliberate hardening: the
 * original read `$this->presenter` directly, which emits a warning under PHP
 * 8.2+ when a consuming model has not declared the property. This checks with
 * isset() first and throws the same PresenterException in that case, so the
 * failure mode is the documented one rather than a dynamic-property warning.
 */
trait PresentableTrait
{
    /**
     * Cached view presenter instance.
     *
     * @var mixed
     */
    protected $presenterInstance;

    /**
     * Prepare a new or cached presenter instance.
     *
     * @return mixed
     *
     * @throws PresenterException
     */
    public function present()
    {
        if (! isset($this->presenter) || ! class_exists($this->presenter)) {
            throw new PresenterException('Please set the $presenter property to your presenter path.');
        }

        if (! $this->presenterInstance) {
            $this->presenterInstance = new $this->presenter($this);
        }

        return $this->presenterInstance;
    }
}
