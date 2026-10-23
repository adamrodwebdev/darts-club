<?php

namespace App\Controller;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Action du domaine appelée par un contrôleur : une règle métier enfreinte (valeur refusée, état incompatible) devient une réponse 422. */
final class DomainCall
{
    /**
     * @template T
     *
     * @param callable(): T $action
     *
     * @return T
     */
    public static function run(callable $action): mixed
    {
        try {
            return $action();
        } catch (\InvalidArgumentException|\LogicException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }
}
