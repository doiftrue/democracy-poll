#!/usr/bin/env bash
#
# Commands to build static binary of "xgettext" last version from source:
# See https://www.gnu.org/software/gettext/  https://ftp.gnu.org/gnu/gettext/
# ```
# $ wget https://ftp.gnu.org/gnu/gettext/gettext-0.24.tar.gz
# $ tar -xzf gettext-0.24.tar.gz
# $ cd gettext-0.24
# $ ./configure --disable-shared --enable-static --disable-tests LDFLAGS="-static"
# $ make -j$(nproc)
# $ gettext-tools/src/xgettext --version
# $ cp  gettext-tools/src/xgettext  /usr/local/bin/xgettext
# ```
#
set -euo pipefail

BASE_DIR="$(cd "$(dirname "$0")/.." && pwd)"  # …/democracy-poll
ANALIZE_DIR=$BASE_DIR

KEYWORDS_PHP=(
	--keyword='__'
	--keyword='_e'
	--keyword='esc_html__'
	--keyword='esc_html_e'
	--keyword='esc_attr__'
	--keyword='esc_attr_e'
	--keyword='_ex:1,2c'
	--keyword='_x:1,2c'
	--keyword='_n:1,2'
	--keyword='_nx:1,2,3c'
	--keyword='_n_noop:1,2'
	--keyword='_nx_noop:1,2,3c'
)

EXCLUDE=(
	-name '*.min.*'
	-o -name '*.map.*'
	-o -path '*__NOTUSED__*'
	-o -path '*/tmp/*'
	-o -path '*/tests/*'
)

build_pot() {
	local ext=$1
	local lang=$2
	local -n keywords=$(echo KEYWORDS_PHP)

	xgettext --language="$lang" "${keywords[@]}" \
		--output="$BASE_DIR/languages/democracy-poll.pot" \
		--add-comments=TRANSLATORS: --force-po --from-code=UTF-8 \
		--directory="$BASE_DIR" \
		$(find "$ANALIZE_DIR" -name "*.$ext" ! \( "${EXCLUDE[@]}" \) | sed "s|$BASE_DIR/||")
}

build_pot php PHP
