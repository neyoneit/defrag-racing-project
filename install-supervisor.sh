#!/bin/bash

# Install and configure Supervisor for auto-starting queue workers

echo "Installing Supervisor..."
sudo apt-get update
sudo apt-get install -y supervisor

echo ""
echo "Copying Supervisor configuration..."
sudo cp supervisor-queue-workers.conf /etc/supervisor/conf.d/defrag-demos-worker.conf

echo ""
echo "Reloading Supervisor configuration..."
sudo supervisorctl reread
sudo supervisorctl update

echo ""
echo "Starting workers..."
sudo supervisorctl start defrag-demos-worker:*

echo ""
echo "✓ Supervisor installed and configured!"
echo ""
echo "Workers will now auto-start on system boot."
echo ""
echo "Useful commands:"
echo "  Check status:  sudo supervisorctl status defrag-demos-worker:*"
echo "  Start workers: sudo supervisorctl start defrag-demos-worker:*"
echo "  Stop workers:  sudo supervisorctl stop defrag-demos-worker:*"
echo "  Restart:       sudo supervisorctl restart defrag-demos-worker:*"
echo "  View logs:     tail -f storage/logs/worker-*.log"
