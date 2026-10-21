#!/usr/bin/env perl
# demo/setup.pl

use strict;
use warnings;

use File::Glob qw(bsd_glob);
use File::Path qw(make_path remove_tree);
use File::Spec;
use FindBin qw($RealBin);

my $ROOT         = $RealBin;
my $VENV_DIR     = File::Spec->catdir( $ROOT, '.venv' );
my $VENV_PYTHON  = File::Spec->catfile( $VENV_DIR, 'bin', 'python' );
my $BUILD_DIR    = File::Spec->catdir( $ROOT, 'build' );
my $LIB_DIR      = File::Spec->catdir( $ROOT, 'public', 'lib' );
my $REQUIREMENTS = File::Spec->catfile( $ROOT, 'requirements.txt' );
my $ENTRY_POINT  = File::Spec->catfile( $ROOT, 'main.py' );

my %COMMANDS = (
    check => \&check,
    build => \&build,
    run   => \&run,
    clean => \&clean,
    all   => sub { check(); build(); run(); },
    help  => \&usage,
);

sub main {
    my ($action) = @ARGV;
    $action //= 'help';

    my $command = $COMMANDS{$action};
    if ( !$command ) {
        usage();
        return 1;
    }

    chdir $ROOT or die "Cannot chdir to $ROOT: $!\n";
    $command->();
    return 0;
}

sub usage {
    print "Usage: perl setup.pl [all|check|build|run|clean]\n";
    return;
}

sub check {
    print "[*] Checking required tools...\n";
    for my $tool (qw(python3 php cmake)) {
        die "[-] '$tool' was not found in PATH.\n" unless command_exists($tool);
    }

    my $ffi =
      capture( 'php', '-r', 'echo extension_loaded("ffi") ? "yes" : "no";' );
    die "[-] PHP FFI extension is not enabled (install php-ffi).\n"
      unless defined $ffi && $ffi eq 'yes';
    print "[+] Tools OK.\n";

    check_venv();
    return;
}

sub check_venv {
    print "[*] Checking virtual environment ($VENV_DIR)...\n";

    if ( !-x $VENV_PYTHON ) {
        print "[-] Virtual environment not found. Creating it...\n";
        run_or_die( 'python3', '-m', 'venv', $VENV_DIR );
    }

    if ( defined capture( $VENV_PYTHON, '-c', 'import PySide6' ) ) {
        print "[+] PySide6 is installed in the venv.\n";
        return;
    }

    print "[-] PySide6 is NOT installed in the venv. Installing...\n";
    run_or_die( $VENV_PYTHON, '-m', 'pip', 'install', '-r', $REQUIREMENTS );
    return;
}

sub build {
    print "[*] Building C++ core with CMake...\n";
    make_path($LIB_DIR);
    run_or_die( 'cmake', '-S', $ROOT, '-B', $BUILD_DIR,
        '-DCMAKE_BUILD_TYPE=Release' );
    run_or_die( 'cmake', '--build', $BUILD_DIR, '--parallel' );
    print "[+] Build completed.\n";
    return;
}

sub run {
    die "[-] Python virtual environment missing. Run: perl setup.pl check\n"
      unless -x $VENV_PYTHON;
    print "[*] Starting Python/PySide6 entry point...\n";
    run_or_die( $VENV_PYTHON, $ENTRY_POINT );
    return;
}

sub clean {
    print "[*] Cleaning build artifacts...\n";
    remove_tree($BUILD_DIR);
    unlink bsd_glob( File::Spec->catfile( $LIB_DIR, 'math_engine.*' ) );
    print "[+] Clean finished.\n";
    return;
}

# --- helpers ---------------------------------------------------------------

# Runs a command (list form: no shell, no quoting issues) and dies on failure.
sub run_or_die {
    my @cmd = @_;
    system(@cmd) == 0
      or die sprintf(
        "[-] Command failed (%s): %s\n",
        $? == -1 ? $! : $? >> 8,
        join( ' ', @cmd )
      );
    return;
}

# Returns the command's stdout on success, undef on failure. stderr is discarded.
sub capture {
    my @cmd = @_;
    open my $stderr_backup, '>&', \*STDERR            or return;
    open STDERR,            '>',  File::Spec->devnull or return;

    my $output;
    if ( open my $fh, '-|', @cmd ) {
        local $/;
        $output = <$fh>;
        close $fh or undef $output;
    }

    open STDERR, '>&', $stderr_backup;
    return $output;
}

sub command_exists {
    my ($cmd) = @_;
    for my $dir ( File::Spec->path ) {
        my $path = File::Spec->catfile( $dir, $cmd );
        return 1 if -f $path && -x _;
    }
    return 0;
}

exit main() unless caller;
