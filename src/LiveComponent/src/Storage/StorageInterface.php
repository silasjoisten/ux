<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\LiveComponent\Storage;

/**
 *  Interface for a storage mechanism used in Symfony UX LiveComponent.
 *
 *  This interface provides methods for persisting, retrieving, and removing
 *  data, ensuring a consistent API for managing state across components. It
 *  is essential for features like multistep forms where data needs to persist
 *  between user interactions.
 *
 * @author Silas Joisten <silasjoisten@proton.me>
 * @author Patrick Reimers <preimers@pm.me>
 * @author Jules Pietri <heahdude@yahoo.fr>
 */
interface StorageInterface
{
    /**
     * Persists a value in the storage using the specified key.
     *
     * This method is used to save the state of a component or any other
     * relevant data that needs to persist across requests or interactions.
     *
     * @param string $key    the unique identifier for the data to store
     * @param mixed  $values the value to be stored
     */
    public function persist(string $key, mixed $values): void;

    /**
     * Removes an entry from the storage based on the specified key.
     *
     * This method is useful for cleaning up data that is no longer needed,
     * such as resetting a form or clearing cached values.
     *
     * @param string $key the unique identifier for the data to remove
     */
    public function remove(string $key): void;

    /**
     * Retrieves a value from the storage by its key.
     *
     * If the specified key does not exist in the storage, this method returns
     * a default value instead. This is commonly used to fetch saved state or
     * configuration for a component.
     *
     * @param string $key     the unique identifier for the data to retrieve
     * @param mixed  $default The default value to return if the key is not found.
     *                        Defaults to an empty array.
     *
     * @return mixed the value associated with the specified key or the default value
     */
    public function get(string $key, mixed $default = []): mixed;
}
