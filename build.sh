#!/bin/bash

# AI Chat for Amazon Bedrock - Build Script
# This script creates a WordPress plugin zip file according to WordPress.org guidelines

# Set variables
PLUGIN_SLUG="ai-chat-for-amazon-bedrock"
VERSION=$(grep "Version:" $PLUGIN_SLUG.php | awk -F' ' '{print $3}')
ZIP_FILE="../$PLUGIN_SLUG-$VERSION.zip"

# Check if zip command is available
if ! command -v zip &> /dev/null; then
    echo "Error: zip command not found. Please install zip."
    exit 1
fi

echo "Building $PLUGIN_SLUG version $VERSION..."

# Create a temporary directory
mkdir -p ../build-tmp
TMP_DIR="../build-tmp/$PLUGIN_SLUG"
mkdir -p $TMP_DIR

# Copy all files to the temporary directory
echo "Copying files..."
cp -R * $TMP_DIR/

# Remove unnecessary files and directories
echo "Removing development files..."
cd $TMP_DIR
rm -rf .git .gitignore .DS_Store build.sh node_modules package.json package-lock.json webpack.config.js composer.json composer.lock phpunit.xml .travis.yml .phpcs.xml .distignore .wordpress-org
find . -name "*.map" -type f -delete
find . -name "*.log" -type f -delete
find . -name ".DS_Store" -type f -delete

# Create the zip file
echo "Creating zip file..."
cd ..
zip -r $ZIP_FILE $PLUGIN_SLUG -x "*.git*" "*.DS_Store" "*.map" "*.log"

# Clean up
echo "Cleaning up..."
rm -rf $PLUGIN_SLUG

echo "Build complete! Plugin zip file created at $ZIP_FILE"
