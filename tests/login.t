#!/usr/bin/perl 
#
# Antoine Bertholon, Decembre 2006
#
# Test gforge-cli.
#
# 1) Test wrong login
# 2) Test good login 
# 3) Test LOGIN (in caps)
# 4) Test short login
#

BEGIN {
    ($dirname, $progname) = ($0 =~ m|^(.*)[/\\]([^/\\]+)$|o) ? ($1, $2) : ('.', $0);
}
use strict;
use vars qw($dirname $progname
	    $cfg_cli_cmd $cfg_wsdl $cfg_project
	    $cfg_user_username $cfg_user_password
	    $cfg_user_short_username
	    $cfg_member_username $cfg_member_password
	    $status
);

require "$dirname/test.config";

use Test::Simple tests => 7;

use File::Temp qw/ tempfile tempdir /;
use File::Path qw/ rmtree /;

my $time = time;

my ($tempdir) = tempdir(CLEANUP => 1);
my ($fh, $tempfile) = tempfile();

chdir $tempdir;

$ENV{'GFORGE_WSDL'} = $cfg_wsdl;

# Test login identified access
system("$cfg_cli_cmd login '--username=xxx' '--password=xxx' ");

ok( $? , "Login with a wrong password");

# Test login identified access
system("$cfg_cli_cmd login '--username=$cfg_user_username' '--password=$cfg_user_password' ");
ok( !$? , "Login with a good password");

sleep(1);
system("$cfg_cli_cmd logout");
ok( !$? , "Logged out");

# Test login identified access
system("$cfg_cli_cmd login '--username=ANTOINE BERTHOLON' '--password=$cfg_user_password' ");
ok( !$? , "Login with LOGIN");

sleep(1);
system("$cfg_cli_cmd logout");
ok( !$? , "Logged out");


# Test login identified access
system("$cfg_cli_cmd login '--username=$cfg_user_short_username' '--password=$cfg_user_password' ");
$status = $?;
ok( $status , "Unable to log with short login");
sleep(1);

if($status){
	ok( 1 , "Logged out");
} else {
	system("$cfg_cli_cmd logout");
	ok( !$? , "Logged out");
}


chdir $tempdir;
rmtree($tempdir, 0, 0);
