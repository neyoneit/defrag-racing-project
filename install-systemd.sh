#!/bin/bash

# Install and configure systemd service for auto-starting queue workers

echo "Installing systemd service..."
sudo cp defrag-queue-workers.service /etc/systemd/system/

echo ""
echo "Reloading systemd daemon..."
sudo systemctl daemon-reload

echo ""
echo "Enabling service to start on boot..."
sudo systemctl enable defrag-queue-workers.service

echo ""
echo "Starting service..."
sudo systemctl start defrag-queue-workers.service

echo ""
echo "✓ Systemd service installed and configured!"
echo ""
echo "Workers will now auto-start on system boot."
echo ""
echo "Useful commands:"
echo "  Check status:  sudo systemctl status defrag-queue-workers"
echo "  Start workers: sudo systemctl start defrag-queue-workers"
echo "  Stop workers:  sudo systemctl stop defrag-queue-workers"
echo "  Restart:       sudo systemctl restart defrag-queue-workers"
echo "  View logs:     sudo journalctl -u defrag-queue-workers -f"
echo "  Disable:       sudo systemctl disable defrag-queue-workers"
