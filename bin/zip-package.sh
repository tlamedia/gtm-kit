#!/bin/sh

plugin_slug="gtm-kit"

# Get the current directory
current_dir=$(pwd)

# Check if the base directory is 'bin'
base_dir=$(basename "$current_dir")
if [ "$base_dir" = "bin" ]; then
    cd ..
fi

# Check if the 'bundled' directory exists, if not create it
[ ! -d "./bundled" ] && mkdir bundled

# Remove the previous zip file if it exists
[ -f "./bundled/${plugin_slug}.zip" ] && rm "./bundled/${plugin_slug}.zip"

# Navigate to the plugin directory
cd ..

# Create a zip file excluding specified directories and files
zip -rq "./${plugin_slug}/bundled/${plugin_slug}.zip" $plugin_slug \
-x "${plugin_slug}/node_modules/*" \
-x "${plugin_slug}/bin/*" \
-x "${plugin_slug}/bundled/*" \
-x "**/.*" \
-x "${plugin_slug}/gulpfile.babel.js" \
-x "${plugin_slug}/package.json" \
-x "${plugin_slug}/package-lock.json" \
-x "${plugin_slug}/tailwind.config.js" \
-x "${plugin_slug}/composer.*" \
-x "${plugin_slug}/*.dist" \
-x "${plugin_slug}/postcss.config.js" \
-x "${plugin_slug}/README.md"

echo "Done"
