import os
import sys
import subprocess

PROJECT_PATH = "/var/www/defrag-racing-project/production"
REPOSITORY_URL = "https://github.com/Defrag-racing/defrag-racing-project.git"
PROJECT_NAME = "defrag-racing-project"

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
        "php artisan storage:link",
        "php artisan migrate --force",
        "php artisan filament:assets",
        "php artisan livewire:publish --assets",
        "php artisan cache:clear",
        "php artisan config:cache",
        "php artisan route:cache",
        "php artisan view:cache",
        "php artisan icons:cache",
        "php artisan scout:import 'App\\Models\\Demo'",
        "php artisan scout:import 'App\\Models\\Map'",
        # Link public assets from deploy directory
        f"rm -rf {PROJECT_PATH}/releases/{name}/public/baseq3",
        f"ln -s {PROJECT_PATH}/deploy/baseq3 {PROJECT_PATH}/releases/{name}/public/baseq3",
        f"test -d {PROJECT_PATH}/deploy/baseq3-hd && ln -sf {PROJECT_PATH}/deploy/baseq3-hd {PROJECT_PATH}/releases/{name}/public/baseq3-hd || true",
        f"rm {PROJECT_PATH}/current",
        f"ln -s {PROJECT_PATH}/releases/{name} {PROJECT_PATH}/current",
        "php artisan optimize:clear",
        # Pre-warm the heaviest cache directly via the service (14s
        # aggregate). Doing this before the octane restart means it
        # runs against the still-current codebase, but the Redis key
        # it writes survives the restart, so the freshly-restarted
        # workers see a warm cache from request #1.
        "php artisan mapstats:rebuild",
        'supervisorctl restart "defrag-racing-octane:*"',
        'supervisorctl restart "defrag-racing-worker:*"',
        "php artisan octane:reload",
        "php artisan queue:restart",
        # Give octane a couple seconds to come up before we hit it.
        "sleep 3",
        # Warm the rest of the public-page caches by triggering the
        # Cache::remember blocks inside the controllers (homepage
        # totals, ranking prebuilt pages, records, community
        # leaderboard, server list, map stats endpoint). cache:clear
        # above wiped them and most have TTLs of 12h+, so the first
        # visitor would otherwise pay full DB cost.
        #
        # `-w` prints status + timing per URL so the deploy log
        # actually shows what got warmed (a silent `-s -o /dev/null`
        # gives no feedback, which made the warm-up indistinguishable
        # from "didn't run").
        'echo "==> Warming public-page caches..."',
        'for url in / /ranking /records /community /servers /maps/stats; do '
        'echo "  - https://defrag.racing$url"; '
        'curl -s -o /dev/null --max-time 30 -w "    HTTP %{http_code}  in %{time_total}s\\n" "https://defrag.racing$url" || echo "    (failed, continuing)"; '
        'done',
    ]

    return cmds

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
        # so warm-up failures (like mapstats:rebuild blowing up
        # silently) are visible in the deploy log instead of being
        # swallowed.
        print(f"\n>>> {cmd}", flush=True)
        result = subprocess.run(cmd, shell=True, cwd=f"{PROJECT_PATH}/releases/{name}")
        if result.returncode != 0:
            print(f"!!! exit {result.returncode} from: {cmd}", flush=True)

if __name__ == "__main__":
    deploy(force="--force" in sys.argv)

