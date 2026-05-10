import os
import subprocess

PROJECT_PATH = "/var/www/defrag-racing-project/production"
REPOSITORY_URL = "https://github.com/Defrag-racing/defrag-racing-project.git"
PROJECT_NAME = "defrag-racing-project"

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
        "curl -s -o /dev/null --max-time 30 https://defrag.racing/ || true",
        "curl -s -o /dev/null --max-time 30 https://defrag.racing/ranking || true",
        "curl -s -o /dev/null --max-time 30 https://defrag.racing/records || true",
        "curl -s -o /dev/null --max-time 30 https://defrag.racing/community || true",
        "curl -s -o /dev/null --max-time 30 https://defrag.racing/servers || true",
        "curl -s -o /dev/null --max-time 30 https://defrag.racing/maps/stats || true",
    ]

    return cmds

def deploy():
    name = get_next_release_name()

    git_clone_cmd = get_git_clone_cmd(name)

    cmds = pipeline_cmds(name)

    subprocess.run(git_clone_cmd, shell=True, cwd=f"{PROJECT_PATH}/releases")

    for cmd in cmds:
        subprocess.run(cmd, shell=True, cwd=f"{PROJECT_PATH}/releases/{name}")

if __name__ == "__main__":
    deploy()

