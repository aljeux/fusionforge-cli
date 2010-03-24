#!/usr/bin/perl 
#
# Antoine Bertholon, December 2006
#
# Test gforge cli
#
# 1) login
# 2) Add package 
# 3) List packages
# 4) Add release 1.0
# 5) Add release 1.1
# 6) List releases
# 7) Add file
# 8) List files
# 9) Get file
# 10) Make Diff to check !
# 11) Logout
#

BEGIN {
    ($dirname, $progname) = ($0 =~ m|^(.*)[/\\]([^/\\]+)$|o) ? ($1, $2) : ('.', $0);
}
use strict;
use vars qw($dirname $progname
	    $cfg_cli_cmd $cfg_wsdl $cfg_project
	    $cfg_user_username $cfg_user_password
	    $cfg_member_username $cfg_member_password
	    $cfg_tracker $cfg_tracker_id $cfg_bug_id $addeed $id $pack_id $rel_id
);

require "$dirname/test.config";

use Test::Simple tests => 15;

use File::Temp qw/ tempfile tempdir /;
use File::Path qw/ rmtree /;

my $time = time;

my ($tempdir) = tempdir(CLEANUP => 1);
my ($fh, $tempfile) = tempfile();

chdir $tempdir;

$ENV{'GFORGE_WSDL'} = $cfg_wsdl;

system("rm /tmp/ID");
# Test login identified access
system("$cfg_cli_cmd login '--username=$cfg_user_username' '--password=$cfg_user_password' ");

ok( !$? , "Login with a good password");

system("$cfg_cli_cmd frs addpackage '--name=New package added from the CLI($$)' '--project=$cfg_project'");

ok( !$? , "Add package for project $cfg_project ");

# lister les packages
system("$cfg_cli_cmd frs packages '--project=$cfg_project' ");
ok( !$? , "List packages of project $cfg_project ");

# Get package id
system("$cfg_cli_cmd frs packages '--project=$cfg_project' | grep $$ | /bin/awk '{ print \$2 }' > /tmp/ID ");
$pack_id=`cat /tmp/ID`;
chomp($pack_id);
ok( !$? , "Get new package id ($pack_id) ");

# Add release for this package
system("$cfg_cli_cmd frs addrelease '--project=$cfg_project' '--package=$pack_id' '--name=Release 1.0'");
ok( !$? , "Add release 'Release 1.0' for package $pack_id project $cfg_project ");

# Add release for this package
system("$cfg_cli_cmd frs addrelease '--project=$cfg_project' '--package=$pack_id' '--name=Release 1.1'");
ok( !$? , "Add release 'Release 1.1' for package $pack_id project $cfg_project ");

# List releases for this package
system("$cfg_cli_cmd frs releases '--project=$cfg_project' '--package=$pack_id' ");
ok( !$? , "List releases of project $cfg_project / package : $pack_id");

# Get release for this package
system("$cfg_cli_cmd frs releases '--project=$cfg_project' '--package=$pack_id' | grep 'Release 1.1' | /bin/awk '{ print \$2 }' > /tmp/ID ");
$rel_id=`cat /tmp/ID`;
chomp($rel_id);
ok( !$? , "Get id of last release added ($rel_id) ");

system("date > /tmp/file$$");
# Add file for this release
system("$cfg_cli_cmd frs addfile '--project=$cfg_project' '--package=$pack_id' '--release=$rel_id' '--file=/tmp/file$$'" );
ok( !$? , "Add file  for project $cfg_project / package : $pack_id / rel : $rel_id");

# List files for this release
system("$cfg_cli_cmd frs files '--project=$cfg_project' '--package=$pack_id' '--release=$rel_id'" );
ok( !$? , "List file  project $cfg_project / package : $pack_id / rel : $rel_id");

# Get file id for this release
system("$cfg_cli_cmd frs files '--project=$cfg_project' '--package=$pack_id' '--release=$rel_id' | grep 'file$$' | /bin/awk '{ print \$2 }' > /tmp/ID ");
$id=`cat /tmp/ID`;
chomp($id);
ok( !$? , "($id) get file ID  project $cfg_project ");

system("$cfg_cli_cmd frs getfile '--project=$cfg_project' '--package=$pack_id' '--release=$rel_id' '--id=$id' '--output=/tmp/out$$'" );
ok( !$? , "Get file ($id) project $cfg_project / $rel_id");
sleep(5);

system("diff /tmp/file$$ /tmp/out$$ >/dev/null");
ok( !$? , "Check file OK");

# List files for this release
system("$cfg_cli_cmd frs files '--project=$cfg_project' '--package=$pack_id' '--release=$rel_id'" );
ok( !$? , "List file  project $cfg_project / package : $pack_id / rel : $rel_id");

system("$cfg_cli_cmd logout");
ok( !$? , "Logout");

chdir $tempdir;
rmtree($tempdir, 0, 0);
