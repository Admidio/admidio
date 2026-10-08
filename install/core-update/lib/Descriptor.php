<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * The signed description of the current release of a channel, **&lt;base&gt;/&lt;channel&gt;/release.json**.
 *
 * It says which version the channel offers, where its package is (one URL or a list of mirrors), the
 * SHA-256 and size of the package, the SHA-256 of the manifest inside it, and the oldest Admidio
 * and PHP versions it can be installed on. Because all of that is signed, the package may come from
 * any mirror, and an installation that is too old is refused before anything is downloaded.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Descriptor
{
    /**
     * @param string $channel
     * @param string $version
     * @param string $versionText
     * @param array<int,string> $packageUrls
     * @param string $packageSha256
     * @param int $packageSize
     * @param string $manifestSha256
     * @param string $minimumSourceVersion
     * @param string $minimumPhpVersion
     * @param string $releasedAt
     * @param string $commit
     */
    private function __construct(
        public readonly string $channel,
        public readonly string $version,
        public readonly string $versionText,
        public readonly array $packageUrls,
        public readonly string $packageSha256,
        public readonly int $packageSize,
        public readonly string $manifestSha256,
        public readonly string $minimumSourceVersion,
        public readonly string $minimumPhpVersion,
        public readonly string $releasedAt,
        public readonly string $commit
    ) {
    }

    /**
     * Verify a descriptor with the keys of its channel and read it.
     * @param string $json
     * @param string $signature
     * @param KeyStatement $keys
     * @param string $channel The channel that was asked for; the descriptor must name the same.
     * @return self
     * @throws UpdaterException
     */
    public static function fromSigned(string $json, string $signature, KeyStatement $keys, string $channel): self
    {
        if ($keys->keysFor($channel) === array()) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_KEYS_INVALID', 'No valid key may sign the channel "' . $channel . '".');
        }
        if (!$keys->verifyFor($channel, $json, $signature)) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_SIGNATURE_INVALID', 'release.json is not signed by a key of the channel "' . $channel . '".');
        }

        $data = json_decode($json, true);
        $urls = is_string($data['packageUrl'] ?? null) ? array($data['packageUrl']) : ($data['packageUrl'] ?? null);

        $problem = match (true) {
            !is_array($data) || ($data['schema'] ?? null) !== 1 => 'schema',
            ($data['channel'] ?? null) !== $channel => 'channel',
            !is_string($data['version'] ?? null) || !Version::isCanonical($data['version']) => 'version',
            !is_array($urls) || $urls === array() || count(array_filter($urls, static fn(mixed $url): bool => is_string($url) && $url !== '')) !== count($urls) => 'packageUrl',
            !is_string($data['packageSha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $data['packageSha256']) !== 1 => 'packageSha256',
            !is_int($data['packageSize'] ?? null) || $data['packageSize'] <= 0 => 'packageSize',
            !is_string($data['manifestSha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $data['manifestSha256']) !== 1 => 'manifestSha256',
            !is_string($data['minimumSourceVersion'] ?? null) || !Version::isCanonical($data['minimumSourceVersion']) => 'minimumSourceVersion',
            !is_string($data['minimumPhpVersion'] ?? null) || preg_match('/^\d+\.\d+(?:\.\d+)?$/', $data['minimumPhpVersion']) !== 1 => 'minimumPhpVersion',
            default => ''
        };
        if ($problem !== '') {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_INFORMATION_INVALID', 'release.json: the field "' . $problem . '" is missing or invalid.');
        }

        return new self(
            $channel,
            $data['version'],
            is_string($data['versionText'] ?? null) ? $data['versionText'] : $data['version'],
            array_values($urls),
            $data['packageSha256'],
            $data['packageSize'],
            $data['manifestSha256'],
            $data['minimumSourceVersion'],
            $data['minimumPhpVersion'],
            is_string($data['releasedAt'] ?? null) ? $data['releasedAt'] : '',
            is_string($data['commit'] ?? null) ? $data['commit'] : ''
        );
    }

    /**
     * Refuse the release when this installation is too old for it.
     * @param string $installedVersion
     * @param string $phpVersion
     * @return void
     * @throws UpdaterException SYS_CORE_UPDATE_SOURCE_TOO_OLD or SYS_CORE_UPDATE_PHP_TOO_OLD
     */
    public function checkCompatibility(string $installedVersion, string $phpVersion): void
    {
        if (Version::compare($installedVersion, $this->minimumSourceVersion) < 0) {
            throw new UpdaterException('SYS_CORE_UPDATE_SOURCE_TOO_OLD', array($this->version, $this->minimumSourceVersion, $installedVersion));
        }
        if (version_compare($phpVersion, $this->minimumPhpVersion) < 0) {
            throw new UpdaterException('SYS_CORE_UPDATE_PHP_TOO_OLD', array($this->version, $this->minimumPhpVersion, $phpVersion));
        }
    }
}
