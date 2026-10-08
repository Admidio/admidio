<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * The signed list of every file of a release, **install/update-manifest.json** inside the package.
 *
 * The descriptor names its SHA-256, and it is signed by the key of the channel, so every byte that
 * reaches the installation is covered by a hash that is covered by a signature. Inside the managed
 * directories it is authoritative: a file it does not list does not belong to the release. Root
 * files are only touched when they are listed in managedRootFiles, and preservePaths are never
 * touched at all.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Manifest
{
    /**
     * @param string $version
     * @param array<int,string> $managedDirectories
     * @param array<int,string> $managedRootFiles
     * @param array<int,string> $preservePaths
     * @param array{php: string, extensions: array<int,string>} $requirements
     * @param int $totalSize
     * @param array<string,string> $files path => SHA-256
     */
    private function __construct(
        public readonly string $version,
        public readonly array $managedDirectories,
        public readonly array $managedRootFiles,
        public readonly array $preservePaths,
        public readonly array $requirements,
        public readonly int $totalSize,
        public readonly array $files
    ) {
    }

    /**
     * Verify the manifest of a package against its descriptor and read it.
     * @param string $json
     * @param string $signature
     * @param KeyStatement $keys
     * @param Descriptor $descriptor
     * @return self
     * @throws UpdaterException
     */
    public static function fromSigned(string $json, string $signature, KeyStatement $keys, Descriptor $descriptor): self
    {
        if (!hash_equals($descriptor->manifestSha256, hash('sha256', $json))) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The manifest does not have the SHA-256 the descriptor names.');
        }
        if (!$keys->verifyFor($descriptor->channel, $json, $signature)) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_SIGNATURE_INVALID', 'The manifest is not signed by a key of the channel "' . $descriptor->channel . '".');
        }

        $data = json_decode($json, true);
        if (!is_array($data) || ($data['version'] ?? null) !== $descriptor->version) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The manifest is not the one of version ' . $descriptor->version . '.');
        }

        $requirements = $data['requirements'] ?? null;
        $problem = match (true) {
            !self::isPathList($data['managedDirectories'] ?? null) => 'managedDirectories',
            !self::isPathList($data['managedRootFiles'] ?? null)
                || array_filter($data['managedRootFiles'], static fn(string $file): bool => str_contains($file, '/')) !== array() => 'managedRootFiles',
            !self::isPathList($data['preservePaths'] ?? null) => 'preservePaths',
            !is_array($requirements) || !is_string($requirements['php'] ?? null) || !is_array($requirements['extensions'] ?? null) => 'requirements',
            !is_int($data['totalSize'] ?? null) || $data['totalSize'] < 0 => 'totalSize',
            !is_array($data['files'] ?? null) || $data['files'] === array() => 'files',
            default => ''
        };
        if ($problem !== '') {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The manifest field "' . $problem . '" is missing or invalid.');
        }

        $caseKeys = array();
        foreach ($data['files'] as $path => $hash) {
            $path = (string)$path;
            if (!Paths::isSafeRelative($path) || !is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
                throw UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The manifest entry "' . $path . '" is invalid.');
            }
            // A case-insensitive file system would write both entries to one file.
            if (isset($caseKeys[strtolower($path)])) {
                throw UpdaterException::withDetail('SYS_CORE_UPDATE_MANIFEST_INVALID', 'The manifest lists "' . $path . '" twice in different case.');
            }
            $caseKeys[strtolower($path)] = true;
        }

        return new self(
            $data['version'],
            $data['managedDirectories'],
            $data['managedRootFiles'],
            $data['preservePaths'],
            array('php' => $requirements['php'], 'extensions' => array_values(array_filter($requirements['extensions'], 'is_string'))),
            $data['totalSize'],
            $data['files']
        );
    }

    /**
     * Whether a value is a list of safe relative paths.
     * @param mixed $value
     * @return bool
     */
    private static function isPathList(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }
        foreach ($value as $path) {
            if (!is_string($path) || !Paths::isSafeRelative($path)) {
                return false;
            }
        }

        return true;
    }
}
