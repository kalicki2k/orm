<?php

namespace ORM\Entity\Type;

enum CascadeType: string
{
    case Persist = 'persist';
    case Remove = 'remove';
}
