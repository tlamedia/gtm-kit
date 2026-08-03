#!/usr/bin/env bash
#
# install-wp-tests.sh
# Downloads a WordPress core checkout + the wordpress-develop PHPUnit test
# helpers, creates the test database, then installs WooCommerce into the
# WP test install and downloads Woo's PHPUnit framework helpers.
#
# Adapted from the canonical WP-CLI scaffold script (which is itself adapted
# from wordpress-develop's `bin/install-wp-tests.sh`). The deviations are:
#
#   - WC_VERSION positional arg (defaults to "latest").
#   - install_woocommerce(): drops the Woo plugin into
#     $WP_CORE_DIR/wp-content/plugins/woocommerce and grabs Woo's
#     `tests/legacy/framework/helpers/*` into $WC_HELPERS_DIR. Both are
#     cached under $TMPDIR/woocommerce-$WC_VERSION/ so switching versions
#     across the matrix doesn't re-download.
#
# Usage:
#   bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [wc-version] [skip-database-creation]
#
# Examples:
#   # Local (DBngin MySQL 8.0 on port 3308, WP 6.9, latest Woo)
#   bin/install-wp-tests.sh gtmkit_tests root '' 127.0.0.1:3308 6.9 latest
#
#   # CI (mysql service container; skip db creation)
#   bin/install-wp-tests.sh gtmkit_tests root root 127.0.0.1:3306 6.9 latest true

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [wc-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
WC_VERSION=${6-latest}
SKIP_DB_CREATE=${7-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib-$WP_VERSION}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress-$WP_VERSION}

download() {
	if command -v curl >/dev/null 2>&1; then
		curl -s "$1" > "$2"
	elif command -v wget >/dev/null 2>&1; then
		wget -nv -O "$2" "$1"
	else
		echo "Error: neither curl nor wget is available."
		exit 1
	fi
}

# Same as download() but fails (non-zero) on HTTP errors and removes the
# partial file. Used for optional Woo helpers that may not exist at every
# Woo tag — caller decides whether to abort or skip.
download_strict() {
	if curl -sfL "$1" -o "$2"; then
		return 0
	fi
	rm -f "$2"
	return 1
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-(beta|RC)[0-9]+$ ]]; then
	WP_BRANCH=${WP_VERSION%\-*}
	WP_TESTS_TAG="branches/$WP_BRANCH"
elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0]$ ]]; then
		# x.x.0 means the first release of the major version — trim ".0".
		WP_TESTS_TAG="tags/${WP_VERSION%??}"
	else
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
else
	download http://api.wordpress.org/core/version-check/1.7/ "$TMPDIR/wp-latest.json"
	LATEST_VERSION=$(grep -o '"version":"[^"]*' "$TMPDIR/wp-latest.json" | sed 's/"version":"//' | head -1)
	if [[ -z "$LATEST_VERSION" ]]; then
		echo "Latest WordPress version could not be found"
		exit 1
	fi
	WP_TESTS_TAG="tags/$LATEST_VERSION"
fi

set -ex

install_wp() {
	# Validate completeness, not just dir existence — a partial download
	# leaves the dir in place but missing wp-settings.php, which trips
	# the WP test bootstrap with an opaque "Failed to open stream" error.
	if [ -d "$WP_CORE_DIR" ] && [ -f "$WP_CORE_DIR/wp-settings.php" ]; then
		return
	fi

	mkdir -p "$WP_CORE_DIR"

	if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
		mkdir -p "$TMPDIR/wordpress-nightly"
		download https://wordpress.org/nightly-builds/wordpress-latest.zip "$TMPDIR/wordpress-nightly/wordpress-nightly.zip"
		unzip -q "$TMPDIR/wordpress-nightly/wordpress-nightly.zip" -d "$TMPDIR/wordpress-nightly/"
		mv "$TMPDIR"/wordpress-nightly/wordpress/* "$WP_CORE_DIR"
	else
		local ARCHIVE_NAME
		if [ "$WP_VERSION" == 'latest' ]; then
			ARCHIVE_NAME='latest'
		elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+ ]]; then
			download https://api.wordpress.org/core/version-check/1.7/ "$TMPDIR/wp-latest.json"
			if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0]$ ]]; then
				LATEST_VERSION=${WP_VERSION%??}
			else
				local VERSION_ESCAPED
				VERSION_ESCAPED=$(echo "$WP_VERSION" | sed 's/\./\\\\./g')
				LATEST_VERSION=$(grep -o '"version":"'"$VERSION_ESCAPED"'[^"]*' "$TMPDIR/wp-latest.json" | sed 's/"version":"//' | head -1)
			fi
			if [[ -z "$LATEST_VERSION" ]]; then
				ARCHIVE_NAME="wordpress-$WP_VERSION"
			else
				ARCHIVE_NAME="wordpress-$LATEST_VERSION"
			fi
		else
			ARCHIVE_NAME="wordpress-$WP_VERSION"
		fi
		download "https://wordpress.org/${ARCHIVE_NAME}.tar.gz" "$TMPDIR/wordpress.tar.gz"
		tar --strip-components=1 -zxmf "$TMPDIR/wordpress.tar.gz" -C "$WP_CORE_DIR"
	fi
}

install_test_suite() {
	# portable in-place argument for both GNU sed and macOS sed
	local ioption
	if [[ $(uname -s) == 'Darwin' ]]; then
		ioption='-i.bak'
	else
		ioption='-i'
	fi

	if [ ! -d "$WP_TESTS_DIR" ]; then
		mkdir -p "$WP_TESTS_DIR"
		svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
		svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "$WP_TESTS_DIR/data"
	fi

	if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
		download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
		# remove all forward slashes in the end
		WP_CORE_DIR_ESCAPED=$(echo "$WP_CORE_DIR" | sed "s:/\+$::")
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR_ESCAPED/':" "$WP_TESTS_DIR/wp-tests-config.php"
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
	fi
}

recreate_db() {
	shopt -s nocasematch
	if [[ $1 =~ ^(y|yes)$ ]]; then
		mysqladmin drop "$DB_NAME" -f --user="$DB_USER" --password="$DB_PASS"$EXTRA
		create_db
		echo -e "Recreated the database ($DB_NAME)."
	else
		echo -e "\nCarrying on without database recreation.\n"
	fi
	shopt -u nocasematch
}

create_db() {
	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_db() {
	if [ "${SKIP_DB_CREATE}" = "true" ]; then
		return 0
	fi

	# parse DB_HOST for port or socket references
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]}
	local DB_SOCK_OR_PORT=${PARTS[1]}
	local EXTRA=""

	if ! [ -z "$DB_HOSTNAME" ] ; then
		if [ $(echo "$DB_SOCK_OR_PORT" | grep -e '^[0-9]\{1,\}$') ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z "$DB_SOCK_OR_PORT" ] ; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif ! [ -z "$DB_HOSTNAME" ] ; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	# create database
	if [ $(mysql --user="$DB_USER" --password="$DB_PASS"$EXTRA --execute='show databases;' | grep ^$DB_NAME$) ]; then
		echo "Reinstalling will delete the existing test database ($DB_NAME)"
		read -p 'Are you sure you want to proceed? [y/N]: ' DELETE_EXISTING_DB
		recreate_db $DELETE_EXISTING_DB
	else
		create_db
	fi
}

# Resolve "latest" to a concrete WC version via the wp.org plugin info API.
# Concrete version is needed for caching ($TMPDIR/woocommerce-<version>) and
# for downloading the matching framework helpers from the source repo at the
# corresponding tag.
resolve_wc_version() {
	if [ "$WC_VERSION" != 'latest' ]; then
		return
	fi
	download 'https://api.wordpress.org/plugins/info/1.0/woocommerce.json' "$TMPDIR/wc-latest.json"
	WC_VERSION=$(grep -o '"version":"[^"]*' "$TMPDIR/wc-latest.json" | head -1 | sed 's/"version":"//')
	if [ -z "$WC_VERSION" ]; then
		echo "ERROR: failed to resolve latest WooCommerce version from wp.org plugin info API"
		exit 1
	fi
}

# Install WooCommerce into the WP test install + cache its PHPUnit framework
# helpers. The wp.org plugin zip is the runtime plugin (no tests/); helpers
# come from the woocommerce/woocommerce GitHub raw URLs.
install_woocommerce() {
	resolve_wc_version

	WC_CACHE_DIR=$TMPDIR/woocommerce-$WC_VERSION
	WC_HELPERS_DIR=$WC_CACHE_DIR/helpers
	export WC_INSTALL_DIR=$WP_CORE_DIR/wp-content/plugins/woocommerce
	export WC_HELPERS_DIR

	# Download + unzip the plugin (cache once per WC version).
	if [ ! -f "$WC_CACHE_DIR/woocommerce/woocommerce.php" ]; then
		rm -rf "$WC_CACHE_DIR/woocommerce"
		mkdir -p "$WC_CACHE_DIR"
		download "https://downloads.wordpress.org/plugin/woocommerce.$WC_VERSION.zip" "$WC_CACHE_DIR/woocommerce.zip"
		unzip -q "$WC_CACHE_DIR/woocommerce.zip" -d "$WC_CACHE_DIR"
		rm "$WC_CACHE_DIR/woocommerce.zip"
	fi

	# Drop the plugin into this WP install (idempotent).
	mkdir -p "$WP_CORE_DIR/wp-content/plugins"
	if [ ! -f "$WC_INSTALL_DIR/woocommerce.php" ]; then
		rm -rf "$WC_INSTALL_DIR"
		cp -R "$WC_CACHE_DIR/woocommerce" "$WC_INSTALL_DIR"
	fi

	# Download Woo's PHPUnit framework helpers (cache once per WC version).
	# These are NOT in the wp.org zip — pulled from the source repo at the
	# matching tag. Some files only exist at certain tags; missing ones are
	# skipped, the bootstrap loads what's present.
	if [ ! -d "$WC_HELPERS_DIR" ]; then
		mkdir -p "$WC_HELPERS_DIR"
		local helper
		for helper in \
			class-wc-helper-product.php \
			class-wc-helper-customer.php \
			class-wc-helper-coupon.php \
			class-wc-helper-shipping.php \
			class-wc-helper-shipping-zones.php \
			class-wc-helper-payment-token.php \
			class-wc-helper-fee.php \
			class-wc-helper-tax.php \
			class-wc-helper-order.php; do
			download_strict \
				"https://raw.githubusercontent.com/woocommerce/woocommerce/$WC_VERSION/plugins/woocommerce/tests/legacy/framework/helpers/$helper" \
				"$WC_HELPERS_DIR/$helper" \
				|| echo "  skipped helper (not present at tag $WC_VERSION): $helper"
		done
	fi

	# Mirror helpers into the WP install so the PHPUnit bootstrap can find
	# them via ABSPATH (no env vars to plumb through). Idempotent.
	mkdir -p "$WP_CORE_DIR/wc-test-helpers"
	cp -R "$WC_HELPERS_DIR/." "$WP_CORE_DIR/wc-test-helpers/"
}

install_wp
install_test_suite
install_db
install_woocommerce
