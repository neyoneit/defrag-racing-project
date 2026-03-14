#!/bin/bash

# Start multiple queue workers for fast parallel demo processing
# Usage: ./start-queue-workers.sh [number_of_workers]
# Default: 8 workers

WORKERS=${1:-8}

echo "Starting $WORKERS queue workers for demos..."

for i in $(seq 1 $WORKERS); do
    echo "Starting worker $i..."
    docker exec -d defrag-racing-project-laravel.test-1 php artisan queue:work redis --queue=demos --tries=3 --timeout=300 --sleep=3
done

echo ""
echo "✓ Started $WORKERS workers!"
echo ""
echo "To monitor workers:"
echo "  docker exec defrag-racing-project-laravel.test-1 php artisan queue:monitor demos"
echo ""
echo "To view queue size:"
echo "  docker exec defrag-racing-project-redis-1 redis-cli LLEN queues:demos"
echo ""
echo "To stop workers:"
echo "  docker exec defrag-racing-project-laravel.test-1 php artisan queue:restart"
