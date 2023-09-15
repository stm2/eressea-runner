## Installing the server

To run an Eressea server, you must download the source code, compile it to a runnable program (called binary), and install it and some additional files in the server directory of your machine.

### Linux system and prerequisites

Eressea was designed to run on a UNIX system. While the code can also be built on any other flavor of UNIX including OS X, as well as Windows, Linux is probably the easiest to use and most widely available operating system, and this guide will focus on that.

If you are not already a Linux user, you will first need a machine. If you have an old PC somewhere you can use it for example. [Debian](https://www.debian.org/download) or [Ubuntu](https://ubuntu.com/download/desktop) Linux are for free and easy to install. We will assume that you are familiar in the basic use of Linux systems.

After you have a fully functional Linux, you need to install some extra packages in order to install Eressea. In a terminal, type the following commands:

```shell
sudo apt-get -y update
sudo apt-get -y install gcc make git cmake liblua5.2-dev libtolua-dev libncurses5-dev libsqlite3-dev libcjson-dev libiniparser-dev libexpat1-dev libutf8proc-dev lua5.2 luarocks libbsd-dev php php-sqlite3 python2 jq zip
luarocks install --local
# only strictly necessary if you want to make changes to the server code
sudo apt-get -y install cppcheck shellcheck clang-tools iwyu
```

TODO
    echo export ERESSEA=~/eressea >> .bash_aliases
    echo export LANG=en_US.UTF-8 >> .bash_aliases
    source .bash_aliases
    [ -d $HOME/log ] || mkdir $HOME/log

### Checking out the code

Eressea is an open-source project hosted on the social coding site [github](https://github.com). We need to clone a copy of the source code before we can build it. Type:

```shell
mkdir -p eressea/server
cd eressea
git clone https://github.com/eressea/server.git git
cd git
git checkout master
```

### Building and installing the code

The game is distributed as platform-independent source code, and we need to compile it into an executable for our platform to make it usable:

~~~shell
cd ~/eressea/git
git submodule update --init
s/cmake-init
luarocks install lunitx --local
s/build && s/runtests && s/install
~~~

If all went well, you should now have a lot of files installed into the ~/eressea/server directory.

You can now proceed to [[Starting a Game]].