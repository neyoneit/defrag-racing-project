#!/bin/bash
# Build Rust rating service for production

set -e

echo "Building Rust rating service..."

# Navigate to Rust project
cd "$(dirname "$0")/rust-rating-service"

# Install Rust if not available
if ! command -v cargo &> /dev/null; then
    echo "Installing Rust..."
    curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y
    source "$HOME/.cargo/env"
fi

# Build release binary
echo "Compiling Rust binary (optimized)..."
cargo build --release

# Copy binary to Laravel storage/app
cp target/release/defrag_rating ../storage/app/defrag_rating
chmod +x ../storage/app/defrag_rating

echo "✓ Rust binary built and installed at storage/app/defrag_rating"
echo "✓ File size: $(du -h ../storage/app/defrag_rating | cut -f1)"
