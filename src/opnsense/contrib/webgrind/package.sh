#!/bin/sh
if [ "$1" == "" ]
then
  echo "Usage: package.sh <tag>"
else
	mkdir package_tmp
	rm webgrind-$1.zip
	cd package_tmp
	svn export https://webgrind.googlecode.com/svn/tags/$1 webgrind
	svn export https://webgrind.googlecode.com/svn/wiki webgrind/docs
	for i in webgrind/docs/*.wiki; do mv "$i" "${i%.wiki}.txt"; done
	rm webgrind/package.sh
	zip -r ../webgrind-$1.zip webgrind
	cd ..
	rm -rf package_tmp
fi
