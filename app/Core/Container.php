<?php
namespace App\Core;

use ReflectionClass;
use Exception;

class Container
{
    /** @var array<class-string, object|callable> */
    protected static array $instances = [];

    /**
     * Retorna uma instância da classe solicitada, resolvendo dependências automaticamente.
     */
    public static function get(string $class)
    {
        // Se já existe uma instância ou um factory registrado
        if (isset(self::$instances[$class])) {
            $entry = self::$instances[$class];

            // Se for um closure/factory, executa e guarda o resultado (lazy load)
            if (is_callable($entry)) {
                $object = $entry();
                self::$instances[$class] = $object;
                return $object;
            }

            return $entry; // objeto pronto
        }

        // Usa reflexão para resolver dependências automaticamente
        $reflector = new ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new Exception("A classe {$class} não é instanciável.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            $instance = new $class();
        } else {
            $dependencies = [];

            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();

                if ($type && !$type->isBuiltin()) {
                    $dependencies[] = self::get($type->getName());
                } elseif ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    throw new Exception("Não foi possível resolver a dependência: {$param->getName()} em {$class}");
                }
            }

            $instance = $reflector->newInstanceArgs($dependencies);
        }

        // Cacheia como singleton
        self::$instances[$class] = $instance;

        return $instance;
    }

    /**
     * Força a injeção manual de uma instância ou registra um factory (closure)
     */
    public static function bind(string $class, callable|object $value): void
    {
        self::$instances[$class] = $value;
    }

    /**
     * Define uma instância diretamente (sem closure)
     */
    public static function set(string $class, object $instance): void
    {
        self::$instances[$class] = $instance;
    }

    /**
     * Limpa o cache de instâncias (útil para testes)
     */
    public static function clear(): void
    {
        self::$instances = [];
    }
}
