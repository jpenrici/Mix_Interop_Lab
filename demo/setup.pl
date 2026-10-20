#!/usr/bin/perl
# demo/setup.pl

use strict;
use warnings;

main();

sub main {
    my $action = $ARGV[0] || 'help';

    if ( $action eq 'check' ) {
        check_venv();
    }
    elsif ( $action eq 'build' ) {
        build();
    }
    elsif ( $action eq 'run' ) {
        run();
    }
    elsif ( $action eq 'all' ) {
        check();
        build();
        run();
    }
    elsif ( $action eq 'clean' ) {
        clean();
    }
    else {
        print "Usage: perl setup.pl [all|check|build|run|clean]\n";
    }

}

sub check {
    check_venv();
}

sub build {
    print "[*] Building C++ Core with CMake...\n";
    system("mkdir -p public/lib");
    system("cmake -B build");
    system("cmake --build build");
    print "[+] Build completed.\n";
}

sub run {
    print "[*] Starting Python/PySide6 Entry Point...\n";
    system("python3 main.py");
}

sub clean {
    print "[*] Cleaning temporary build files...\n";
    system("rm -rf ./build public/lib/*.so public/lib/*.dll");
    print "[+] Clean finished.\n";
}

sub check_venv {
    my $venv_dir   = ".venv";
    my $python_bin = "/bin/python3";    # Linux

    print "[*] Checking virtual environment ($venv_dir)...\n";

    if ( -d $venv_dir ) {
        print "[+] Virtual environment directory exists.\n";

        print "[*] Checking if PySide6 is installed...\n";
        my $output = qx{$python_bin -c "import PySide6; print(1)" 2>/dev/null};

        if ( $? == 0 && $output =~ /1/ ) {
            print "[+] PySide6 is installed in the venv.\n";
        }
        else {
            print "[-] PySide6 is NOT installed in the venv.\n";
            system("$venv_dir/bin/python -m pip install PySide6");
        }
    }
    else {
        print "[-] Virtual environment ($venv_dir) not found.\n";
        system("$python_bin -m venv .venv");
        check_venv();
    }
}
