<?php

namespace App\Support\Presenter;

/**
 * In-house replacement for Laracasts\Presenter\Contracts\PresentableInterface.
 * See PresenterException for why this package was vendored in-house.
 */
interface PresentableInterface
{
    /**
     * Prepare a new or cached presenter instance.
     *
     * @return mixed
     */
    public function present();
}
