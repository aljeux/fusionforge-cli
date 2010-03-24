#!/usr/bin/perl 
#
# Antoine Bertholon, December 2006
#
# Test gforge-cli
#
# 1) login
# 2) set priority to 5 for $cfg_bug_id
# 3) list bugs properties in f1
# 4) set priority to 2 for $cfg_bug_id
# 5) list bugs properties in f2
# 6) make a diff to check
# 7) logout
#

BEGIN {
    ($dirname, $progname) = ($0 =~ m|^(.*)[/\\]([^/\\]+)$|o) ? ($1, $2) : ('.', $0);
}
use strict;
use vars qw($dirname $progname
	    $cfg_cli_cmd $cfg_wsdl $cfg_project
	    $cfg_user_username $cfg_user_password
	    $cfg_member_username $cfg_member_password
	    $cfg_tracker $cfg_tracker_id $cfg_bug_id $addeed
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
system("$cfg_cli_cmd login '--username=$cfg_user_username' '--password=$cfg_user_password' ");

ok( !$? , "Login with a good password");

system("echo 'y' | $cfg_cli_cmd tracker update '--type=$cfg_tracker_id' '--priority=5' '--project=$cfg_project' '--id=$cfg_bug_id'");

ok( !$? , "Modify bug $cfg_bug_id set priority to 5");

# lister les bugs du tracker XXX (nnn)
system("$cfg_cli_cmd tracker list '--type=$cfg_tracker_id' '--project=$cfg_project' > ./d1");
ok( !$? , "List Bugs of tracker $cfg_tracker_id");

system("echo 'y' | $cfg_cli_cmd tracker update '--type=$cfg_tracker_id' '--priority=2' '--project=$cfg_project' '--id=$cfg_bug_id'");

ok( !$? , "Modify bug $cfg_bug_id set priority to 2");

system("$cfg_cli_cmd tracker list '--type=$cfg_tracker_id' '--project=$cfg_project' > ./d2");
ok( !$? , "List Bugs of tracker $cfg_tracker_id");

system("diff d1 d2");
ok( $? , "$cfg_bug_id updated !");


# logout
system("$cfg_cli_cmd logout");
ok( !$? , "Logout");

chdir $tempdir;
rmtree($tempdir, 0, 0);
