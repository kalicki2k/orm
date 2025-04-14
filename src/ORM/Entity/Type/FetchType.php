<?php

namespace ORM\Entity\Type;

enum FetchType: string
{
    case Lazy = "lazy";
    case Eager = "eager";
}
