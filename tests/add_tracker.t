#!/usr/bin/perl 
#
# Antoine Bertholon, December 2006
#
# Test gforge-cli.
#
# 1) login
# 2) list bugs properties in f1
# 3) add a bug
# 4) list bugs properties in f2
# 5) make a diff to check
# 6) logout
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

use Test::Simple tests => 6;

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

# lister les bugs du tracker XXX (nnn)
#../gforge.php tracker list --type=323 --project=testab

system("$cfg_cli_cmd tracker list '--type=$cfg_tracker_id' '--project=$cfg_project' >./l1");
ok( !$? , "List Bugs of tracker $cfg_tracker_id");

# Ajouter un bug
#../gforge.php tracker add --type=323 --summary="Test 2" --details="details ....." --project=testab
system("echo 'y' | $cfg_cli_cmd tracker add '--type=$cfg_tracker_id' '--summary=Test_2' '--details=details' '--project=$cfg_project' >/dev/null");
ok( !$? , "Add a bug for $cfg_tracker_id");

system("$cfg_cli_cmd tracker list '--type=$cfg_tracker_id' '--project=$cfg_project' >./l2");
ok( !$? , "List again bugs of tracker $cfg_tracker_id to check");

system("diff l1 l2 >/dev/null");
ok( $? , "Tracker added !");

# logout
system("$cfg_cli_cmd logout");
ok( !$? , "Logout");

chdir $tempdir;
rmtree($tempdir, 0, 0);
