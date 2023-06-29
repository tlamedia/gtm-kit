#!/bin/sh

export LC_CTYPE=en_US.UTF-8
export LC_ALL=en_US.UTF-8

echo $1
# Functions

# Output colorized strings
#
# Color codes:
# 0 - black
# 1 - red
# 2 - green
# 3 - yellow
# 4 - blue
# 5 - magenta
# 6 - cian
# 7 - white
output() {
  echo "$(tput setaf "$1")$2$(tput sgr0)"
}

if [ ! $1 ]; then
	output 3 "Please enter the version number, for example, 1.0.0:"
	read -r VERSION
fi

output 2 "Updating version numbers in files..."

# Update version number in readme.txt.
perl -X -i -pe 's/Stable tag:*.+/Stable tag: '${VERSION}'/' readme.txt
output 2 "Version numbers updated in readme.txt."

# Update version in main plugin file.
perl -X -i -pe 's/Version:*.+/Version:     '${VERSION}'/' gtm-kit.php

perl -X -i -pe "s/const GTMKIT_VERSION *.+/const GTMKIT_VERSION = '"${VERSION}"';"'/' gtm-kit.php



# Update version in package.json.
perl -X -i -pe 's/"version":*.+/"version": "'${VERSION}'",/' package.json

# Update version in composer.json.
perl -X -i -pe 's/"version":*.+/"version": "'${VERSION}'",/' composer.json

output 2 "Version numbers updated in main plugin file, readme.txt, package.json and composer.json."
