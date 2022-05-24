<?php

namespace Ninh\ShoppingCart;

use Illuminate\Support\Collection;

class CartItemOptions extends Collection
{
    public function __get($key)
    {
        return $this->get($key);
    }
}
