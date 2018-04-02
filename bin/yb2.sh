#! /usr/bin/env bash

set -e


# If directory exists, remove
if [ -e $1 ]; then
  echo "Remove old directory...";
  rm -rf ./$1
fi

echo "Check out from github..."
git clone git@github.com:hametuha/$1.git $1
cd $1

# checkout Tag
if [ $# = 2 ]; then
  git checkout refs/tags/$2
  echo "Check out $2..."
fi

# Install libraries
if [ -e package.json ]; then
	npm install &&  npm run package
fi
if [ -e composer.json ]; then
	composer install --no-dev
fi

# Erase files
dirs=(.git bower_components src bin node_modules .gitignore tests .travis.yml phpcs.ruleset.xml phpunit.xml.dist README.md package.json gulpfile.js )
for dir in ${dirs[@]}; do
	echo "Remove $dir"
	rm -rf $dir
done

# compile
echo "Compile done.";
