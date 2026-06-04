<?php

namespace App\Services;

/**
 * Converts the extension's UI settings into Quake 3 console commands the bot
 * executes. Direct port of the bridge's convert_settings_to_commands() so the
 * web-native path produces byte-identical cvar commands.
 */
class DefragliveSettingsService
{
    /**
     * cvar map ported verbatim from server.py setting_configs. toggle => the
     * two values for off/on; range => value passed through; vid_restart =>
     * needs a single trailing vid_restart; format_decimals => one-decimal float
     * (gamma).
     */
    private const CONFIGS = [
        'triggers'    => ['cvar' => 'r_rendertriggerBrushes', 'type' => 'toggle', 'values' => [0, 1]],
        'sky'         => ['cvar' => 'r_fastsky', 'type' => 'toggle', 'values' => [1, 0]],
        'clips'       => ['cvar' => 'r_renderClipBrushes', 'type' => 'toggle', 'values' => [0, 1]],
        'slick'       => ['cvar' => 'r_renderSlickSurfaces', 'type' => 'toggle', 'values' => [0, 1]],
        'brightness'  => ['cvar' => 'r_mapoverbrightbits', 'type' => 'range', 'vid_restart' => true],
        'picmip'      => ['cvar' => 'r_picmip', 'type' => 'range', 'vid_restart' => true],
        'fullbright'  => ['cvar' => 'r_fullbright', 'type' => 'toggle', 'values' => [0, 1], 'vid_restart' => true],
        'gamma'       => ['cvar' => 'r_gamma', 'type' => 'range', 'format_decimals' => true],
        'drawgun'     => ['cvar' => 'cg_drawgun', 'type' => 'toggle', 'values' => [2, 1]],
        'angles'      => ['cvar' => 'df_chs1_Info6', 'type' => 'toggle', 'values' => [0, 40]],
        'lagometer'   => ['cvar' => 'cg_lagometer', 'type' => 'toggle', 'values' => [0, 1]],
        'snaps'       => ['cvar' => 'mdd_snap', 'type' => 'toggle', 'values' => [0, 3]],
        'cgaz'        => ['cvar' => 'mdd_cgaz', 'type' => 'toggle', 'values' => [0, 1]],
        'speedinfo'   => ['cvar' => 'df_chs1_Info5', 'type' => 'toggle', 'values' => [0, 23]],
        'speedorig'   => ['cvar' => 'df_drawSpeed', 'type' => 'toggle', 'values' => [0, 1]],
        'inputs'      => ['cvar' => 'df_chs0_draw', 'type' => 'toggle', 'values' => [0, 1]],
        'obs'         => ['cvar' => 'df_chs1_Info7', 'type' => 'toggle', 'values' => [0, 50]],
        'nodraw'      => ['cvar' => 'df_mp_NoDrawRadius', 'type' => 'toggle', 'values' => [100, 100000]],
        'thirdperson' => ['cvar' => 'cg_thirdperson', 'type' => 'toggle', 'values' => [0, 1]],
        'miniview'    => ['cvar' => 'df_ghosts_MiniviewDraw', 'type' => 'toggle', 'values' => [0, 6]],
        'gibs'        => ['cvar' => 'cg_gibs', 'type' => 'toggle', 'values' => [0, 1]],
        'blood'       => ['cvar' => 'com_blood', 'type' => 'toggle', 'values' => [0, 1]],
    ];

    /** @return string[] list of "cvar value" command strings (+ trailing vid_restart if needed). */
    public function convertToCommands(array $settings): array
    {
        $commands = [];
        $needsVidRestart = false;

        foreach ($settings as $key => $value) {
            if (!isset(self::CONFIGS[$key])) {
                continue;
            }

            $cfg = self::CONFIGS[$key];

            if (!empty($cfg['vid_restart'])) {
                $needsVidRestart = true;
            }

            if ($cfg['type'] === 'toggle') {
                $resolved = is_bool($value)
                    ? ($value ? $cfg['values'][1] : $cfg['values'][0])
                    : $value;
            } else { // range
                $resolved = !empty($cfg['format_decimals'])
                    ? number_format((float) $value, 1)
                    : $value;
            }

            $commands[] = $cfg['cvar'] . ' ' . $resolved;
        }

        // vid_restart must come AFTER all the setting commands (parity with the
        // bridge), and only once.
        if ($needsVidRestart) {
            $commands[] = 'vid_restart';
        }

        return $commands;
    }
}
