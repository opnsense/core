#! /usr/bin/env bash
######################################################################
#                                                                    #
# Copyright (c) 2013, Niamkik <niamkik@gmail.com>                    # 
# All rights reserved.                                               #
#                                                                    #
# Redistribution and use in source and binary forms, with or without #
# modification, are permitted provided that the following conditions #
# are met:                                                           #
#                                                                    #
# 1. Redistributions of source code must retain the above copyright  #
#    notice, this list of conditions and the following disclaimer.   #
#                                                                    #
# 2. Redistributions in binary form must reproduce the above         #
#    copyright notice, this list of conditions and the following     #
#    disclaimer in the documentation and/or other materials provided #
#    with the distribution.                                          #
#                                                                    #
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND             #
# CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,        #
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF           #
# MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE           #
# DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS  #
# BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,           #
# EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED    #
# TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,      #
# DATA, OR PROFITS; OR BUSINESS INTERRUPTION)HOWEVER CAUSED AND ON   #
# ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR #
# TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF #
# THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF    #
# SUCH DAMAGE.                                                       #
#                                                                    #
# The views and conclusions contained in the software and            #
# documentation are those of the authors and should not be           #
# interpreted as representing official policies, either expressed or #
# implied, of the FreeBSD Project.                                   #
#                                                                    #
# ================================================================== #
#                                                                    #
# Usage: put "source _pkg.bash" into ~/.bashrc or /etc/bash.bashrc.  #
#                                                                    #
######################################################################

######################################################################
# Global function (generate dynamic package list e.g.)               #
######################################################################

_pkg_installed () {
    /usr/local/sbin/pkg query "%n-%v"
}

_pkg_available_name () {
    /usr/local/sbin/pkg rquery "%n"
}

_pkg_available () {
    /usr/local/sbin/pkg rquery "%n-%v"
}

######################################################################
# pkgng subfunction for pkg subcommand.                              #
######################################################################

_pkgng_add () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=()

    small_info="Registers a package and installs it on the system"
    large_info=""
}

_pkgng_audit () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=('-F' '-q')
    lopts=()

    small_info="Reports vulnerable packages"
    large_info=""
}

_pkgng_autoremove () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=()
    small_info="Removes orphan packages"
    large_info=""
}

_pkgng_check () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=('-B' '-d' '-r' '-s' '-v' '-g' '-x' '-X' '-a')
    lopts=()
    small_info="Checks for missing dependencies and database consistency"
    large_info=""
}

_pkgng_clean () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=()
    small_info="Cleans old packages from the cache"
    large_info=""
}

_pkgng_create () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=('-r' '-m' '-f' '-l' '-o' '-g' '-x' '-X' '-a')
    lopts=()
    small_info="Creates software package distributions"
    large_info=""
}

_pkgng_delete () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=('-y' '-n' '-f' '-g' '-x' '-X' '-a')
    lopts=()
    small_info="Deletes packages from the database and the system"
    large_info=""
}

_pkgng_fetch () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=('-y' '-L' '-q' '-g' '-x' '-X' '-a')
    lopts=()
    small_info="Fetches packages from a remote repository"
    large_info=""
}

_pkgng_help () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=()
    small_info="Displays help information"
    large_info=""
}

_pkgng_info () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=('-e' '-d' '-r' '-l' '-o' '-p' '-D' 
          '-f' '-q' '-g' '-x' '-X' '-F' '-a')
    lopts=()
    small_info="Displays information about installed packages"
    large_info=""
}

_pkgng_install () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=('-y' '-n' '-f' '-R' '-L' '-x' '-X' '-g')
    lopts=()
    small_info="Installs packages from remote package repositories"
    large_info=""
}

_pkgng_query () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=('-g' '-x' '-X' '-F' '-e' '-a')
    lopts=()
    small_info="Queries information about installed packages"
    large_info=""
}

_pkgng_register () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=('-l' '-d' '-f' '-m' '-a' '-i')
    lopts=()
    small_info="Registers a package into the local database"
    large_info=""
}

_pkgng_remove () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=()
    small_info="Deletes packages from the database and the system"
    large_info=""
}

_pkgng_repo () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=()
    small_info="Creates a package repository catalogue"
    large_info=""
}

_pkgng_rquery () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=('-g' '-x' '-X' '-e' '-a')
    lopts=()
    small_info="Queries information in repository catalogues"
    large_info=""
}

_pkgng_search () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=('-x' '-X' '-g')
    lopts=()
    small_info="Performs a search of package repository catalogues"
    large_info=""
}

_pkgng_set () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=('-o' '-A' '-y' '-g' '-x' '-X' '-a')
    lopts=('(-o)-A[Mark as automatic or not]'
           '(-A)-o[Change the origin]'
           '-y[Assume yes when asked for confirmation]'
           '(-g -x -X)-a[Process all packages]'
           '(-x -X -a)-g[Process packages that match the glob pattern]'
           '(-g -X -a)-x[Process packages that match the regex pattern]'
           '(-g -x -a)-X[Process packages that match the extended regex pattern]')

    small_info="Modifies information about packages in the local database"
    large_info=""
}

_pkgng_shell () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=('-q[Be quiet]'
           '(-l)-r[Display stats only for the local package database]'
           '(-r)-l[Display stats only for the remote package database(s)]')

    small_info="Opens a debug shell"
    large_info=""
}

_pkgng_shlib () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=('-f[Force updating]'
           '-q[Be quiet]')

    small_info="Displays which packages link against a specific shared library"
    large_info=""
}

_pkgng_stats () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=()
    small_info="Displays package database statistics"
    large_info=""
}

_pkgng_update () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=()
    small_info="Updates package repository catalogues"
    large_info=""
}

_pkgng_updating () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=()
    small_info="Displays UPDATING information for a package"
    large_info=""
}

_pkgng_upgrade () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=()
    small_info="Performs upgrades of packaged software distributions"
    large_info=""
}

_pkgng_version () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=('(-P -R)-I[Use INDEX file]'
           '(-R -I)-P[Force checking against the ports tree]'
           '(-I -P)-R[Use remote repository]'
           '-o[Display package origin, instead of package name]'
           '-q[Be quiet]'
           '-v[Be verbose]'
           '(-L)-l[Display only the packages for given status flag]'
           '(-l)-L[Display only the packages without given status flag]')

    small_info="Displays the versions of installed packages"
    large_info=""
}

_pkgng_which () {
    local cur prev opts lopts
    COMPREPLY=()

    opts=()
    lopts=()
    small_info="Displays which package installed a specific file"
    large_info=""
}

######################################################################
# Main function for completion, only for "pkg" command. Other        #
# subcommand use function like _pkgng_[subfunction].                 #
######################################################################

_pkg () {

    local cur prev opts lopts
    COMPREPLY=()

    # get command name
    cur="${COMP_WORDS[COMP_CWORD]}"
    
    # get first arguments
    prev="${COMP_WORDS[COMP_CWORD-1]}"
    
    # init opts for first completion
    opts='add audit autoremove check clean create delete
          fetch help info install query rquery search set shell
          shlib stats update updating upgrade version which'

    # init lopts for second completion with details
    lopts=( 'add[Registers a package and installs it on the system]'
            'audit[Reports vulnerable packages]'
            'autoremove[Removes orphan packages]'
            'check[Checks for missing dependencies and database consistency]'
            'clean[Cleans old packages from the cache]'
            'convert[Convert database from/to pkgng]'
            'create[Creates software package distributions]'
            'delete[Deletes packages from the database and the system]'
            'fetch[Fetches packages from a remote repository]'
            'help[Displays help information]'
	    'info[Displays information about installed packages]'
            'install[Installs packages from remote package repositories]'
            'query[Queries information about installed packages]'
            'register[Registers a package into the local database]'
            'remove[Deletes packages from the database and the system]'
            'repo[Creates a package repository catalogue]'
            'rquery[Queries information in repository catalogues]'
            'search[Performs a search of package repository catalogues]'
            'set[Modifies information about packages in the local database]'
            'shell[Opens a debug shell]'
            'shlib[Displays which packages link against a specific shared library]'
            'stats[Displays package database statistics]'
            'update[Updates package repository catalogues]'
            'updating[Displays UPDATING information for a package]'
            'upgrade[Performs upgrades of packaged software distributions]'
            'version[Displays the versions of installed packages]'
            'which[Displays which package installed a specific file]' )

    # switch on second arguments
    case "${prev}" in 

 	add) 
	    COMPREPLY=( $(compgen -A file *.t?z ) ) && \
	    return 0 
            ;;

        audit) 
	    COMPREPLY=( 
		'-F[Fetch the database before checking.]'
		'-q[Quiet]'
		$(compgen -F _pkg_installed) 
	    )
	    return 0 
            ;;

        autoremove) 
	    COMPREPLY=( $(compgen) ) && \
		return 0 ;;

        check) 
	    COMPREPLY=(
		'-B[reanalyse the shared libraries]' 
		'-d[check for and install missing dependencies]' 
		'-r[recompute sizes and checksums of installed]' 
		'-s[find invalid checksums]' 
		'-v[Be verbose]' 
		'(-g -x -X)-a[Process all packages]' 
		'(-x -X -a)-g[Process packages that match the glob pattern]'
		'(-g -X -a)-x[Process packages that match the regex pattern]'
		'(-g -x -a)-X[Process packages that match the extended regex pattern]'
	    )
	    return 0 
	    ;;

        clean) 
	    return 0 ;;
	
        convert)
            # _arguments -s \
            # '-r[Revert conversion]' \
            # return 0
            return 0;;

        create) 
	    COMPREPLY=(
		'-r[Root directory] -/'
		'-m[Manifest directory] -/'
		'-f[format]'
		'-o[Ouput directory] -/'
		'(-g -x -X)-a[Process all packages]'
		'(-x -X -a)-g[Process packages that match the glob pattern]'
		'(-g -X -a)-x[Process packages that match the regex pattern]'
		'(-g -x -a)-X[Process packages that match the extended regex pattern]'
		'*:Package:_pkg_installed'
	    )
	    return 0 
	    ;;

        delete|remove) 
	    COMPREPLY=(
		'(-y)-n[Assume yes when asked for confirmation]'
		'(-n)-y[Assume no (dry run) when asked for confirmation]'
		'-f[Force the package(s) to be removed]'
		'(-g -x -X)-a[Process all packages]'
		'(-x -X -a)-g[Process packages that match the glob pattern]'
		'(-g -X -a)-x[Process packages that match the regex pattern]'
		'(-g -x -a)-X[Process packages that match the extended regex pattern]'
		'*:Package:_pkg_installed'
	    )
	    return 0 
	    ;;

        fetch) 
	    COMPREPLY=(
		'-y[Assume yes when asked for confirmation]'
		'-L[Do not try to update the repository metadata]'
		'-q[Be quiet]'
		'(-g -x -X)-a[Process all packages]'
		'(-x -X -a)-g[Process packages that match the glob pattern]'
		'(-g -X -a)-x[Process packages that match the regex pattern]' 
		'(-g -x -a)-X[Process packages that match the extended regex pattern]'
		'*:Available packages:_pkg_available'
	    )
	    return 0 
	    ;;

        help) 
	    COMPREPLY=() && \
		return 0 ;;

        info) 
	    COMPREPLY=(
		'(-e -d -r -l -o -p -D)-f[Displays full information]'
		'(-f -d -r -l -o -p -D)-e[Returns 0 if <pkg-name> is installed]'
		'(-e -f -r -l -o -p -D)-d[Displays the dependencies]'
		'(-e -d -f -l -o -p -D)-r[Displays the reverse dependencies]'
		'(-e -d -r -f -o -p -D)-l[Displays all files]'
		'(-e -d -r -l -f -p -D)-o[Displays origin]'
		'(-e -d -r -l -o -f -D)-p[Displays prefix]'
		'(-e -d -r -l -o -p -f)-D[Displays message]'
		'-q[Be quiet]'
		'(-g -x -X -F)-a[Process all packages]'
		'(-x -X -a -F)-g[Process packages that match the glob pattern]'
		'(-g -X -a -F)-x[Process packages that match the regex pattern]'
		'(-g -x -a -F)-X[Process packages that match the extended regex pattern]'
		'(-g -x -X -a)-F[Process the specified package]'
		'*:Package:_pkg_installed'
	    )
	    return 0 
	    ;;

        install) 
	    COMPREPLY=(
		'(-y)-n[Assume yes when asked for confirmation]'
		'(-n)-y[Assume no (dry run) when asked for confirmation]'
		'-f[Force reinstallation if needed]'
		'-R[Reinstall every package depending on matching expressions]'
		'-L[Do not try to update the repository metadata]'
		'(-x -X)-g[Process packages that match the glob pattern]'
		'(-g -X)-x[Process packages that match the regex pattern]'
		'(-g -x)-X[Process packages that match the extended regex pattern]'
		'*:Available packages:_pkg_available'
	    )
	    return 0 
	    ;;

        query) 
	    COMPREPLY=(
		'(-g -x -X -F -e)-a[Process all packages]'
		'(-x -X -a -F -e)-g[Process packages that match the glob pattern]'
		'(-g -X -a -F -e)-x[Process packages that match the regex pattern]'
		'(-g -x -a -F -e)-X[Process packages that match the extended regex pattern]'
		'(-g -x -X -a -F)-e[Process packages that match the evaluation]'
		'(-g -x -X -a -e)-F[Process the specified package]'
		':Ouput format:'
	    )
	    return 0 
	    ;;

        register) 
	    COMPREPLY=(
		'-l[register as a legacy format]'
		'-d[mark the package as an automatic dependency]'
		'-f[packing list file]'
		'-m[metadata directory]'
		'-a[ABI]'
		'-i[input path (aka root directory)]'
	    )
	    return 0 ;;

        repo) 
	    COMPREPLY=() && \
		return 0 ;;

        rquery) 
	    COMPREPLY=(
		'(-g -x -X -e)-a[Process all packages]'
		'(-x -X -a -e)-g[Process packages that match the glob pattern]'
		'(-g -X -a -e)-x[Process packages that match the regex pattern]'
		'(-g -x -a -e)-X[Process packages that match the extended regex pattern]'
		'(-g -x -X -a)-e[Process packages that match the evaluation]'
	    )
	    return 0 ;;

        search) 
	    COMPREPLY=(
		'(-x -X)-g[Process packages that match the glob pattern]'
		'(-g -X)-x[Process packages that match the regex pattern]'
		'(-g -x)-X[Process packages that match the extended regex pattern]'
	    )
	    return 0 ;;

        set) 
	    COMPREPLY=(
		'(-o)-A[Mark as automatic or not]'
		'(-A)-o[Change the origin]'
		'-y[Assume yes when asked for confirmation]'
		'(-g -x -X)-a[Process all packages]'
		'(-x -X -a)-g[Process packages that match the glob pattern]'
		'(-g -X -a)-x[Process packages that match the regex pattern]'
		'(-g -x -a)-X[Process packages that match the extended regex pattern]'
	    )
	    return 0 ;;

        shell) 
	    COMPREPLY=() && \
		return 0 ;;

        shlib) 
	    COMPREPLY=() && \
		return 0 ;;

        stats) 
	    COMPREPLY=(
		'-q[Be quiet]'
		'(-l)-r[Display stats only for the local package database]'
		'(-r)-l[Display stats only for the remote package database(s)]' 
	    )
	    return 0 ;;

        update) 
	    COMPREPLY=(
		'-f[Force update]'
		'-q[Be quiet]'
	    )
	    return 0 ;;

        updating) 
	    COMPREPLY=(
		'-d[Only entries newer than date are shown]'
		'-f[Defines a alternative location of the UPDATING file]'
	    )
	    return 0 
	    ;;

        upgrade) 
	    COMPREPLY=(
		'(-y)-n[Assume no (dry run) when asked for confirmation]' 
		'(-n)-y[Assume yes when asked for confirmation]' 
		'-f[Upgrade/Reinstall everything]' 
		'-L[Do not try to update the repository metadata]'
	    )
	    return 0 
	    ;;

        version) 
	    COMPREPLY=(
		'(-P -R)-I[Use INDEX file]'
		'(-R -I)-P[Force checking against the ports tree]'
		'(-I -P)-R[Use remote repository]'
		'-o[Display package origin, instead of package name]'
		'-q[Be quiet]'
		'-v[Be verbose]'
		'(-L)-l[Display only the packages for given status flag]'
		'(-l)-L[Display only the packages without given status flag]'
	    )
	    return 0 
	    ;;

        which) 
	    COMPREPLY=( $(compgen -W "$(compgen -A file)") ) && \
		return 0 
	    ;;
    esac

    # if doesn't exist, return opts
    COMPREPLY=( $(compgen -W "${opts}" -- ${cur}) )
}

complete -F _pkg pkg
