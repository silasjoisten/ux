<?php


/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\LiveComponent\Storage;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Implementation of the StorageInterface using Symfony's session mechanism.
 *
 * This class provides a session-based storage solution for managing data
 * persistence in Symfony UX LiveComponent. It leverages the Symfony
 * `RequestStack` to access the session and perform operations such as
 * storing, retrieving, and removing data.
 *
 * Common use cases include persisting component state, such as form data
 * or multistep workflow progress, across user interactions.
 *
 * @author Silas Joisten <silasjoisten@proton.me>
 * @author Patrick Reimers <preimers@pm.me>
 * @author Jules Pietri <heahdude@yahoo.fr>
 */
final class SessionStorage implements StorageInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function persist(string $key, mixed $values): void
    {
        $this->requestStack->getSession()->set($key, $values);
    }

    public function remove(string $key): void
    {
        $this->requestStack->getSession()->remove($key);
    }

    public function get(string $key, mixed $default = []): mixed
    {
        return $this->requestStack->getSession()->get($key, $default);
    }
}
