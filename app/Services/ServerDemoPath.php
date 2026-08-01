<?php

namespace App\Services;

/**
 * Everything a serverdemo tells us about itself, read out of its own path.
 *
 * The MDD recordsystem writes one demo per finished run:
 *
 *   <credential>/serverdemos/server_<rs id>/<physics>/<map>[<time ms>][<mdd id>].dm_68
 *
 * - map, time in ms and the player's MDD id come from the filename
 * - physics from the directory holding the file
 * - the rs server id from the `server_<id>` segment
 * - the first segment is the SFTP credential that uploaded it
 *
 * The player is identified by MDD id, so nothing here has to guess at
 * nicknames the way the public demo pipeline does.
 *
 * Layout is not fixed depth: most credentials produce
 * `<user>/serverdemos/server_<id>/<physics>/<file>` but older ones have no
 * `serverdemos` level, so the `server_<id>` segment is located rather than
 * assumed to be at a given depth.
 */
class ServerDemoPath
{
    /** Directories the recordsystem uses for physics. Anything else -> null. */
    private const PHYSICS = ['cpm', 'vq3'];

    /** Revoked accounts are parked here by the provisioner; not live data. */
    public const SKIP_DIRS = ['_revoked'];

    /**
     * @return array{owner_dir:string,filename:string,rs_server_id:?int,physics:?string,map_name:string,time_ms:?int,mdd_id:?int}|null
     *         null when the path is not a demo file at all
     */
    public static function parse(string $path): ?array
    {
        $path = ltrim($path, '/');
        $segments = explode('/', $path);
        $filename = array_pop($segments);

        if ($filename === null || $segments === []) {
            return null;
        }

        if (! preg_match('/\.dm_\d+$/i', $filename)) {
            return null;
        }

        $ownerDir = $segments[0];

        if (in_array($ownerDir, self::SKIP_DIRS, true)) {
            return null;
        }

        $rsServerId = null;
        foreach ($segments as $segment) {
            if (preg_match('/^server_(\d+)$/i', $segment, $m)) {
                $rsServerId = (int) $m[1];
            }
        }

        // Physics is the directory the file sits in, and only when it really
        // is one - a demo dropped straight into server_<id>/ must not turn its
        // parent directory name into a physics value.
        $physics = null;
        $parent = end($segments);
        if ($parent !== false && in_array(strtolower($parent), self::PHYSICS, true)) {
            $physics = strtolower($parent);
        }

        $parsed = self::parseFilename($filename);

        return [
            'owner_dir'    => $ownerDir,
            'filename'     => $filename,
            'rs_server_id' => $rsServerId,
            'physics'      => $physics,
            'map_name'     => $parsed['map'],
            'time_ms'      => $parsed['time'],
            'mdd_id'       => $parsed['mdd_id'],
        ];
    }

    /**
     * `<map>[<time_ms>][<mdd_id>].dm_68`, plus the underscore variant older
     * demos use. A name that matches neither still yields a map, because the
     * file is real and belongs in the index either way.
     *
     * @return array{map:string,time:?int,mdd_id:?int}
     */
    public static function parseFilename(string $filename): array
    {
        $base = preg_replace('/\.dm_\d+$/i', '', $filename);

        if (preg_match('/^(.+?)\[(\d+)\]\[(\d+)\]$/', $base, $m)) {
            return ['map' => $m[1], 'time' => (int) $m[2], 'mdd_id' => (int) $m[3]];
        }

        if (preg_match('/^(.+?)_(\d+)_(\d+)$/', $base, $m)) {
            return ['map' => $m[1], 'time' => (int) $m[2], 'mdd_id' => (int) $m[3]];
        }

        return ['map' => $base, 'time' => null, 'mdd_id' => null];
    }
}
