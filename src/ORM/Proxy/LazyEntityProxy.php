<?php

namespace ORM\Proxy;

class LazyEntityProxy
{
    /**
     * The actual entity instance once loaded.
     */
    private ?object $entity = null;

    /**
     * A callable that loads the actual entity when needed.
     * The callable should return the loaded entity.
     */
    private $initializer;

    /**
     * Constructor accepts an initializer callable.
     *
     * @param callable $initializer The callback that returns the real entity.
     */
    public function __construct(callable $initializer)
    {
        $this->initializer = $initializer;
    }

    /**
     * Magic getter: Initializes the proxy if needed and delegates property access.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        $this->initialize();
        return $this->entity->$name;
    }

    /**
     * Magic setter: Initializes the proxy if needed and delegates property assignment.
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, $value)
    {
        $this->initialize();
        $this->entity->$name = $value;
    }

    /**
     * Magic __call to delegate method calls to the actual entity.
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        $this->initialize();
        return $this->entity->$name(...$arguments);
    }

    /**
     * Ensures the real entity is loaded.
     */
    private function initialize(): void
    {
        if ($this->entity === null) {
            $this->entity = call_user_func($this->initializer);
        }
    }
}
