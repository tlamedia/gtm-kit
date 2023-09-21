#!/bin/sh


# Get the current directory
current_dir=$(pwd)

# Extract the last directory from the current path
base_dir=$(basename "$current_dir")

# Check if the base directory is 'bin'
if [ "$base_dir" = "bin" ]; then
    cd ..
fi

rm ./bundled/gtm-kit.zip
zip -rq ./bundled/gtm-kit.zip * -x "node_modules/*" -x "bin/*" -x "bundled/*" -x "**/.*" -x gulpfile.babel.js -x package.json -x package-lock.json -x tailwind.config.js -x "composer.*" -x "*.dist" -x postcss.config.js -x README.md

Echo Done
