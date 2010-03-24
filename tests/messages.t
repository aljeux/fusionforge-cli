#!/usr/bin/perl 
#
# Antoine Bertholon, December 2006
#
# Test gforge cli
#
# 1) login
# 2) Add message for $cfg_bug_id
# 3) List message for $cfg_bug_id
# 4) logout
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

use Test::Simple tests => 4;

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

system("$cfg_cli_cmd tracker addmessage '--type=$cfg_tracker_id' '--project=$cfg_project' '--id=$cfg_bug_id' '--message=test add message for $cfg_bug_id'");

ok( !$? , "Add message for $cfg_bug_id");

# lister les messages
system("$cfg_cli_cmd tracker messages '--type=$cfg_tracker_id' '--project=$cfg_project' '--id=$cfg_bug_id' ");
ok( !$? , "List Messages of tracker $cfg_tracker_id");

system("$cfg_cli_cmd logout");
ok( !$? , "Logout");

chdir $tempdir;
rmtree($tempdir, 0, 0);
