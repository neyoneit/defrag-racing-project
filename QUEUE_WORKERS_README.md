# Queue Workers Quick Reference

## What are Queue Workers?

Queue workers process uploaded demos in the background. Running multiple workers in parallel significantly speeds up demo processing.

**Without workers**: Demos sit in queue, not processed
**With 1 worker**: Demos process one at a time (~15-25 sec each)
**With 8 workers**: Demos process 8 at a time (8x faster!)

---

## Quick Start Options

### Option 1: Manual Start (Development/Testing)

Start 8 workers manually:
```bash
./start-queue-workers.sh 8
```

**Pros**: Quick and easy, good for testing
**Cons**: Workers stop when you close terminal or reboot system

---

### Option 2: Auto-Start with Supervisor (RECOMMENDED)

Install once, workers auto-start forever:
```bash
./install-supervisor.sh
```

**Pros**:
- Workers auto-start on system boot
- Auto-restart if they crash
- Easy to monitor and control
- Best for production

**Common Commands**:
```bash
# Check if workers are running
sudo supervisorctl status defrag-demos-worker:*

# Restart workers
sudo supervisorctl restart defrag-demos-worker:*

# Stop workers
sudo supervisorctl stop defrag-demos-worker:*

# View logs
tail -f storage/logs/worker-*.log
```

---

### Option 3: Auto-Start with Systemd

Alternative to Supervisor:
```bash
./install-systemd.sh
```

**Pros**: Built into Linux, no extra software needed
**Cons**: Slightly less flexible than Supervisor

**Common Commands**:
```bash
# Check status
sudo systemctl status defrag-queue-workers

# Restart
sudo systemctl restart defrag-queue-workers

# View logs
sudo journalctl -u defrag-queue-workers -f
```

---

## How Many Workers Should I Run?

**General rule**: 1-1.5 workers per CPU core

- **4 CPU cores**: 4-6 workers
- **8 CPU cores**: 8-12 workers
- **16 CPU cores**: 12-16 workers

**To check your CPU cores**:
```bash
nproc
```

---

## Monitoring Queue Progress

### Check queue size (how many demos waiting):
```bash
docker exec defrag-racing-project-redis-1 redis-cli LLEN queues:demos
```

### Check failed jobs:
```bash
docker exec defrag-racing-project-laravel.test-1 php artisan queue:failed
```

### Retry all failed jobs:
```bash
docker exec defrag-racing-project-laravel.test-1 php artisan queue:retry all
```

---

## Troubleshooting

### Workers not processing demos?

**Check if workers are running:**
```bash
# With Supervisor:
sudo supervisorctl status defrag-demos-worker:*

# With Systemd:
sudo systemctl status defrag-queue-workers

# Manual workers:
ps aux | grep "queue:work"
```

**If not running, start them:**
```bash
# Supervisor:
sudo supervisorctl start defrag-demos-worker:*

# Systemd:
sudo systemctl start defrag-queue-workers

# Manual:
./start-queue-workers.sh 8
```

### Demos processing slowly?

- Check CPU usage: `top` or `htop`
- If CPU not fully utilized, add more workers
- Check logs for errors: `tail -f storage/logs/laravel.log`

### High memory usage?

- Reduce number of workers
- Workers auto-restart after 1000 jobs to prevent memory leaks

---

## Performance Expectations

**500 demos with 8 workers**: ~30-60 minutes

| Workers | Time for 500 Demos |
|---------|-------------------|
| 1 | ~2-4 hours |
| 4 | ~45-90 minutes |
| 8 | ~30-60 minutes |
| 16 | ~20-40 minutes |

---

## Which Option Should I Choose?

**For Development/Testing**:
- Use `./start-queue-workers.sh 8`

**For Production**:
- Use `./install-supervisor.sh` (RECOMMENDED)
- Or `./install-systemd.sh` (alternative)

Both production options ensure workers automatically start on system boot and restart if they crash.

---

## Full Documentation

See [DEMO_UPLOAD_OPTIMIZATION.md](DEMO_UPLOAD_OPTIMIZATION.md) for complete technical details.
