#!/bin/bash

set -euo pipefail

PLUGIN_VERSION=${1-""}

if [ "${PLUGIN_VERSION}" == "" ]; then
	echo "You need to specify plugin's version as a first argument"
	exit 1
fi

DIST="./dist"
SLUG="stoplist"
TARGET_DIR="$DIST/$SLUG"

ARCHIVE_NAME="${SLUG}-${PLUGIN_VERSION}.zip"

rm -rf ${DIST:?}/
mkdir -p $TARGET_DIR

cp index.php $TARGET_DIR/
cp stoplist.php $TARGET_DIR/

cd $DIST

zip -r $ARCHIVE_NAME $SLUG > /dev/null

rm -rf $SLUG

declare -a FilesArray=(
	"index.php"
	"stoplist.php"
)

if [[ $(unzip -Z1 $ARCHIVE_NAME | grep -c -E -v "\/$") -ne ${#FilesArray[@]} ]]; then
	echo "Unmatched archive's files count"
	exit 1
fi

for file in "${FilesArray[@]}"; do
	if [[ ! $(unzip -Z1 $ARCHIVE_NAME | grep $SLUG/$file) ]]; then
		echo "File $SLUG/${file} does not exist in the archive"
		exit 1
	fi
done
