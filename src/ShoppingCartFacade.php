<?php

namespace Ninh\ShoppingCart;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ninh\ShoppingCart\Skeleton\SkeletonClass
 */
class ShoppingCartFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'shoppingcart';
    }
}
