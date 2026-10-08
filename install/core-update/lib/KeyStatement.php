<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * The statement of the keys that may sign updates, **release-keys.json**.
 *
 * Only the root key signs this statement, and the root key is kept offline. The statement names the
 * keys that sign releases and nightly builds and the channels each of them may sign, and the keys
 * that are revoked. Replacing a key - planned or after a leak - is therefore a new statement with a
 * higher sequence number, which every installation picks up on its next check; nobody has to touch
 * an installation.
 *
 * ```
 * {"schema": 1, "sequence": 3,
 *  "keys": [{"id": "release-2026", "channels": ["stable", "beta"], "publicKey": "-----BEGIN PUBLIC KEY-----..."},
 *           {"id": "nightly-2026", "channels": ["master"], "publicKey": "..."}],
 *  "revoked": ["release-2025"]}
 * ```
 *
 * Three statements are known at a time: the one the updater was shipped with, the one fetched from
 * the update server, and the highest one this installation has seen before. The one with the
 * highest sequence number wins, so an old statement - from an old package or a stale mirror - can
 * never bring a revoked key back.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class KeyStatement
{
    /**
     * @param int $sequence
     * @param array<int,array{id: string, channels: array<int,string>, publicKey: string}> $keys
     * @param array<int,string> $revoked
     * @param string $json The signed bytes.
     * @param string $signature The content of the .sig file.
     */
    private function __construct(
        public readonly int $sequence,
        private readonly array $keys,
        private readonly array $revoked,
        public readonly string $json,
        public readonly string $signature
    ) {
    }

    /**
     * Verify a statement against the root key and read it.
     * @param string $json
     * @param string $signature
     * @param string $rootKey PEM of the public root key.
     * @return self
     * @throws UpdaterException SYS_CORE_UPDATE_KEYS_INVALID
     */
    public static function fromSigned(string $json, string $signature, string $rootKey): self
    {
        if (!Signature::verify($json, $signature, $rootKey)) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_KEYS_INVALID', 'The signature of release-keys.json does not match the root key.');
        }

        $data = json_decode($json, true);
        if (!is_array($data) || ($data['schema'] ?? null) !== 1 || !is_int($data['sequence'] ?? null) || $data['sequence'] < 1
            || !is_array($data['keys'] ?? null) || !is_array($data['revoked'] ?? array())) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_KEYS_INVALID', 'release-keys.json has not the expected form.');
        }

        $keys = array();
        foreach ($data['keys'] as $key) {
            if (!is_array($key) || !is_string($key['id'] ?? null) || preg_match('/^[A-Za-z0-9._-]+$/', $key['id']) !== 1
                || !is_array($key['channels'] ?? null) || !is_string($key['publicKey'] ?? null)
                || @openssl_pkey_get_public($key['publicKey']) === false) {
                throw UpdaterException::withDetail('SYS_CORE_UPDATE_KEYS_INVALID', 'release-keys.json contains an invalid key.');
            }
            $keys[] = array(
                'id' => $key['id'],
                'channels' => array_values(array_filter($key['channels'], 'is_string')),
                'publicKey' => $key['publicKey']
            );
        }

        return new self($data['sequence'], $keys, array_values(array_filter($data['revoked'] ?? array(), 'is_string')), $json, $signature);
    }

    /**
     * The statement with the highest sequence number. Statements that are missing or do not verify
     * are left out; there must be at least one that does.
     * @param array<int,self|null> $statements
     * @return self
     * @throws UpdaterException SYS_CORE_UPDATE_KEYS_INVALID
     */
    public static function newest(array $statements): self
    {
        $newest = null;
        foreach ($statements as $statement) {
            if ($statement instanceof self && ($newest === null || $statement->sequence > $newest->sequence)) {
                $newest = $statement;
            }
        }

        if ($newest === null) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_KEYS_INVALID', 'No valid release-keys.json is available.');
        }

        return $newest;
    }

    /**
     * Read a statement from a file and its .sig file next to it, or **null** if there is none or it
     * does not verify.
     * @param string $file
     * @param string $rootKey
     * @return self|null
     */
    public static function fromFile(string $file, string $rootKey): ?self
    {
        if (!is_file($file) || !is_file($file . '.sig')) {
            return null;
        }

        try {
            return self::fromSigned((string)file_get_contents($file), (string)file_get_contents($file . '.sig'), $rootKey);
        } catch (UpdaterException) {
            return null;
        }
    }

    /**
     * Write the statement with its signature, for example as the highest one seen so far.
     * @param string $file
     * @return void
     */
    public function save(string $file): void
    {
        if (@file_put_contents($file . '.sig', $this->signature, LOCK_EX) === false
            || @file_put_contents($file, $this->json, LOCK_EX) === false) {
            throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
        }
    }

    /**
     * The public keys that may sign a channel, as key ID => PEM. A revoked key is never among them,
     * even if the statement lists it.
     * @param string $channel
     * @return array<string,string>
     */
    public function keysFor(string $channel): array
    {
        $keys = array();
        foreach ($this->keys as $key) {
            if (in_array($channel, $key['channels'], true) && !in_array($key['id'], $this->revoked, true)) {
                $keys[$key['id']] = $key['publicKey'];
            }
        }

        return $keys;
    }

    /**
     * Whether data was signed by one of the keys of a channel.
     * @param string $channel
     * @param string $data
     * @param string $signature
     * @return bool
     */
    public function verifyFor(string $channel, string $data, string $signature): bool
    {
        foreach ($this->keysFor($channel) as $publicKey) {
            if (Signature::verify($data, $signature, $publicKey)) {
                return true;
            }
        }

        return false;
    }
}
