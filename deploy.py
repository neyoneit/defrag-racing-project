import os
import shutil
import sys
import subprocess

PROJECT_PATH = "/var/www/defrag-racing-project/production"
REPOSITORY_URL = "https://github.com/Defrag-racing/defrag-racing-project.git"
PROJECT_NAME = "defrag-racing-project"

# How many releases survive a deploy. Enough to roll back a few deploys, few
# enough that the pile stays small - each release carries its own vendor/ and
# node_modules/, so they run about 1.2 GB apiece.
KEEP_RELEASES = 5

def get_deployed_sha():
    """Commit SHA of the release the `current` symlink points at, i.e. what is
    live right now. None if there's no current release yet (first deploy) or it
    can't be read."""
    current = f"{PROJECT_PATH}/current"
    if not os.path.exists(current):
        return None
    result = subprocess.run(
        "git rev-parse HEAD", shell=True, cwd=current,
        capture_output=True, text=True
    )
    if result.returncode != 0:
        return None
    return result.stdout.strip()

def get_remote_sha():
    """Commit SHA at the tip of origin/master right now (what a fresh clone
    would check out). None if it can't be read - e.g. a network error or
    GitHub replication lag returning nothing. The full clone below is the
    source of truth; this is only used for the 'anything new?' pre-check."""
    result = subprocess.run(
        f"git ls-remote {REPOSITORY_URL} refs/heads/master",
        shell=True, capture_output=True, text=True
    )
    if result.returncode != 0 or not result.stdout.strip():
        return None
    return result.stdout.split()[0].strip()

def get_next_id():
	result = 0
	for file in os.listdir(f"{PROJECT_PATH}/releases"):
		if file.startswith(PROJECT_NAME) == False:
			continue

		id = int(file.split('-')[-1])

		if id > result:
			result = id

	return result + 1

def get_next_release_name():
    id = get_next_id()

    return PROJECT_NAME + "-" + str(id)

def get_git_clone_cmd(name):
    return f"git clone {REPOSITORY_URL} {name}"

def pipeline_cmds(name):
    cmds = [
        "COMPOSER_ALLOW_SUPERUSER=1 composer install --optimize-autoloader --no-dev --no-interaction",
        "npm ci",
        "npm run build",
        f"ln -s {PROJECT_PATH}/deploy/.env {PROJECT_PATH}/releases/{name}/.env",
        f"rm -rdf {PROJECT_PATH}/releases/{name}/storage",
        f"ln -s {PROJECT_PATH}/deploy/storage {PROJECT_PATH}/releases/{name}/storage",
        "./build-rust.sh",
        # The huffman decoder the demo parser leans on, built from the C source
        # that is in the repo. Only the compiled object is gitignored, so every
        # release starts without it and huffman.py silently falls back to the
        # pure-Python reader - correct, but several times slower, and on a big
        # demo slow enough to run into the parser's own timeout. Nothing breaks
        # if the build fails (that fallback is exactly what we had until now),
        # so it must never stop a deploy - but say so in the log.
        'echo "==> Building the demo parser huffman extension..."',
        "cd app/Services/DemoProcessor/bin/demoparser "
        "&& python3 setup.py build_ext --inplace "
        "&& python3 -c \"import _q3huff; print('    huffman extension ready')\" "
        "|| echo '!!! build failed - demos will be parsed in pure Python (slow but correct)'",
        "php artisan storage:link",
        "php artisan migrate --force",
        "php artisan filament:assets",
        # Without this a newly added Filament resource answers 404 to a logged
        # in admin while `route:list` happily shows its route, because the panel
        # serves whatever component list it cached. Every other cache below is
        # rebuilt here; this one was the odd one out and cost a deploy.
        "php artisan filament:cache-components",
        "php artisan livewire:publish --assets",
        "php artisan cache:clear",
        "php artisan config:cache",
        "php artisan route:cache",
        "php artisan view:cache",
        "php artisan icons:cache",
        # Link public assets from deploy directory
        f"rm -rf {PROJECT_PATH}/releases/{name}/public/baseq3",
        f"ln -s {PROJECT_PATH}/deploy/baseq3 {PROJECT_PATH}/releases/{name}/public/baseq3",
        f"test -d {PROJECT_PATH}/deploy/baseq3-hd && ln -sf {PROJECT_PATH}/deploy/baseq3-hd {PROJECT_PATH}/releases/{name}/public/baseq3-hd || true",
        f"rm {PROJECT_PATH}/current",
        f"ln -s {PROJECT_PATH}/releases/{name} {PROJECT_PATH}/current",
        "php artisan optimize:clear",
        'supervisorctl restart "defrag-racing-octane:*"',
        'supervisorctl restart "defrag-racing-worker:*"',
        "php artisan queue:restart",
        # Give octane a couple seconds to come up before we hit it.
        "sleep 3",
        # Prove the restart above actually took, because once it did not and
        # nothing said so. Supervisor signals the swoole master, waits
        # stopwaitsecs, and if the master is still winding its workers down it
        # declares it stopped and spawns a replacement - which cannot bind
        # port 8000, dies, and lands in BACKOFF. The original keeps serving,
        # orphaned, from a release directory the prune below then deletes. The
        # site stays up on old code and starts 404ing its own assets, while
        # the deploy log says nothing at all.
        #
        # So compare the app bundle this release built against the one the
        # running octane hands out, and if they differ, take the port back the
        # hard way and check again. Never exits non-zero: a deploy that has
        # already swung the symlink must not stop half way, and every outcome
        # is printed for the log either way.
        r"""set +e
check() { curl -s --max-time 10 -H 'Host: defrag.racing' http://127.0.0.1:8000/maps | grep -o 'assets/app-[^"]*\.js' | head -1; }
want=$(grep -o 'assets/app-[^"]*\.js' public/build/manifest.json | head -1)
got=$(check)
if [ -n "$want" ] && [ "$want" = "$got" ]; then
    echo "    octane is serving $want"
else
    echo "!!! octane is serving '$got', this release built '$want' - a stale process is holding port 8000"
    pkill -f 'artisan octane:start'
    sleep 3
    supervisorctl start 'defrag-racing-octane:*'
    sleep 5
    got=$(check)
    if [ "$want" = "$got" ]; then
        echo "    recovered, octane is serving $want"
    else
        echo "!!! STILL STALE: serving '$got', expected '$want' - check: supervisorctl status"
    fi
fi
exit 0""",
        # Flush the application cache AFTER the new code is confirmed to be
        # serving, not before.
        #
        # `optimize:clear` above runs while the symlink has already swung but
        # octane is still the OLD process. Every request landing in that window
        # is served by the old code, and any of them that warms a cache - the
        # Demos Top box, a map page, a profile - writes what the OLD code
        # computed into a cache the NEW code then reads for an hour. That is
        # how a corrected demo name can sit fixed in the database and wrong on
        # the page after a deploy, with nothing in the log to suggest why.
        #
        # Cheap: the caches this drops are all rebuilt on first request.
        "php artisan cache:clear",

        # Search indexing, and it has to be down here rather than up with the
        # other cache rebuilds. SCOUT_QUEUE is on, so `scout:import` only
        # dispatches jobs - and run before the restarts above, those jobs get
        # picked up by the workers of the *previous* release, which build each
        # document with the old toSearchableArray(). Any newly indexed field
        # silently never lands, which is how the map search shipped without its
        # name_exact and had to be re-imported by hand after the deploy.
        "php artisan scout:import 'App\\Models\\Demo'",
        "php artisan scout:import 'App\\Models\\Map'",
        # The heaviest cache on the site - aggregates over 15k maps, a bit
        # over two minutes cold - which cache:clear above just wiped. It is
        # only ever read by /maps/stats, so there is no reason for the whole
        # deploy to sit and wait for it: detach it and let it finish on its
        # own. Redis keeps the key across the octane restart, and the daily
        # 04:00 schedule takes over from there. Whoever opens /maps/stats
        # inside that window still pays for a cold build, same as before.
        'echo "==> Rebuilding MapStats cache in the background (~2 min, see storage/logs/mapstats-rebuild.log)..."',
        "nohup php artisan mapstats:rebuild >> storage/logs/mapstats-rebuild.log 2>&1 &",
        # Warm the rest of the public-page caches by triggering the
        # Cache::remember blocks inside the controllers (homepage
        # totals, ranking prebuilt pages, records, community
        # leaderboard, server list). cache:clear
        # above wiped them and most have TTLs of 12h+, so the first
        # visitor would otherwise pay full DB cost.
        #
        # `-w` prints status + timing per URL so the deploy log
        # actually shows what got warmed (a silent `-s -o /dev/null`
        # gives no feedback, which made the warm-up indistinguishable
        # from "didn't run").
        'echo "==> Warming public-page caches..."',
        # /maps/stats is deliberately not in this list - the background rebuild
        # above is already building exactly that payload, and asking for the
        # page here would just start a second cold build alongside it.
        'for url in / /ranking /records /community /servers; do '
        'echo "  - https://defrag.racing$url"; '
        'curl -s -o /dev/null --max-time 30 -w "    HTTP %{http_code}  in %{time_total}s\\n" "https://defrag.racing$url" || echo "    (failed, continuing)"; '
        'done',
        # Republish the core bundles (dfsv-core.tar for linux, dfsv-core-win.zip
        # for windows) from the freshly deployed builder code. --force skips the
        # engine/mod-unchanged no-op, so a deploy that only changed the builder
        # itself still refreshes them (the nightly schedule keeps picking up
        # engine/mod updates between deploys). A failure (GitHub or q3defrag.org
        # down) only flags the deploy log - rerun
        # `php artisan bundle:build-server --force` by hand then.
        'echo "==> Rebuilding core server bundles..."',
        "php artisan bundle:build-server --force",
    ]

    return cmds

def prune_releases():
    """Delete every release but the KEEP_RELEASES newest.

    Nothing ever removed them, so they had piled up to 29 releases / 36 GB
    before this was written. The live release is resolved through the
    `current` symlink instead of assumed to be the highest id, so a rollback
    (which repoints `current` at an older release) can never delete the code
    that is actually running. rmtree unlinks symlinks rather than following
    them, so the .env / storage / baseq3 links inside a release are removed
    without touching what they point at in production/deploy/.
    """
    releases_dir = f"{PROJECT_PATH}/releases"
    live = os.path.realpath(f"{PROJECT_PATH}/current")

    releases = []
    for name in os.listdir(releases_dir):
        if not name.startswith(PROJECT_NAME + "-"):
            continue
        try:
            releases.append((int(name.split("-")[-1]), name))
        except ValueError:
            continue
    releases.sort()

    doomed = [name for _, name in releases[:-KEEP_RELEASES]]
    if not doomed:
        print(f"    {len(releases)} release(s), nothing to prune.", flush=True)
        return

    for name in doomed:
        path = f"{releases_dir}/{name}"
        if os.path.realpath(path) == live:
            print(f"    keeping {name}: it is the live release", flush=True)
            continue
        print(f"    removing {name}", flush=True)
        try:
            shutil.rmtree(path)
        except OSError as e:
            # Never fail the deploy over housekeeping - the site is already up.
            print(f"!!! could not remove {name}: {e}", flush=True)

def deploy(force=False):
    # Skip the whole deploy if the live release is already on the remote
    # master tip - cloning + rebuilding + restarting octane for an identical
    # codebase is pure churn. `--force` overrides this (e.g. to redeploy the
    # same commit after a .env change or to re-warm caches).
    remote = get_remote_sha()
    deployed = get_deployed_sha()

    if remote is None:
        print("!!! Could not read remote master (git ls-remote failed); "
              "deploying anyway.", flush=True)
    elif deployed is None:
        print(f"==> No current release yet; deploying remote master "
              f"{remote[:10]}.", flush=True)
    elif remote == deployed:
        if force:
            print(f"==> Nothing new (live release already at {remote[:10]}), "
                  f"but --force given - redeploying anyway.", flush=True)
        else:
            # deploy.py is always run by hand, so just ask - lets you still
            # redeploy the same commit (e.g. after a .env change) by answering
            # yes, without needing --force.
            print(f"==> Nothing new: live release is already at remote master "
                  f"{remote[:10]}.", flush=True)
            answer = input("    Deploy anyway? [y/N] ").strip().lower()
            if answer not in ("y", "yes"):
                print("    Aborted.", flush=True)
                return
            print("    Redeploying the same commit anyway.", flush=True)
    else:
        print(f"==> New commits to deploy: live {deployed[:10]} -> remote "
              f"{remote[:10]}.", flush=True)

    name = get_next_release_name()

    git_clone_cmd = get_git_clone_cmd(name)

    cmds = pipeline_cmds(name)

    subprocess.run(git_clone_cmd, shell=True, cwd=f"{PROJECT_PATH}/releases")

    for cmd in cmds:
        # Print the command we're about to run + flag non-zero exits
        # so failures (a warm-up curl, an import, a bundle rebuild) are
        # visible in the deploy log instead of being swallowed. The one
        # backgrounded step reports nothing here by definition - its
        # output goes to storage/logs/mapstats-rebuild.log.
        print(f"\n>>> {cmd}", flush=True)
        result = subprocess.run(cmd, shell=True, cwd=f"{PROJECT_PATH}/releases/{name}")
        if result.returncode != 0:
            print(f"!!! exit {result.returncode} from: {cmd}", flush=True)

    # Last, so a failure here cannot stop the site from going live.
    print(f"\n==> Pruning old releases (keeping {KEEP_RELEASES})...", flush=True)
    prune_releases()

    # A shell that was sitting in `current` before this ran is now standing in
    # the release we just replaced: bash resolved that symlink when you cd'd,
    # and it holds the old directory, not the new one. Artisan there runs the
    # previous deploy's code, and once that release is pruned it runs nothing
    # at all. The script cannot fix that from here - a child process cannot
    # move its parent shell - so it says so, with the line to paste.
    print("\n==> Live.", flush=True)
    print("    Any shell already open in `current` is now in the OLD release. Re-enter it:", flush=True)
    print(f"\n    cd {PROJECT_PATH}/current\n", flush=True)

if __name__ == "__main__":
    deploy(force="--force" in sys.argv)

