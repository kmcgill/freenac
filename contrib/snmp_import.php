#!/usr/bin/php -- -f
<?php
/**
 * contrib/snmp_import.php
 *
 * Long description for file:
 * This script that is meant to be used during the deployment phase to add
 *   ports to the DB that we expect to be managed by NAC.
 * - get the actual configuration of non-trunk ports of switches and 
 *   populate the port table.  
 * - ignore ports with vlan=0, and take the current vlan so that it can be used as a port 
 *   default vlan
 * - the output is SWL that you should review debore executing
 * see also README:snmp_import, snmp_defs.inc, config.inc
 * Enable $debug_flag1 and $debug_flag2 the first time you use this.
 *
 * On IOS do "show ip arp" - "show vlan"
 *        or "sh ip arp vrf insec"
 * On CatOS so "show port status"
 * Further reading: 
 *    http://www.cisco.com/public/sw-center/netmgmt/cmtk/mibs.shtml
 *    The "getif" tool for exploring MIBs.
 *
 * PHP version 5
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation.
 *
 * @package			FreeNAC
 * @author			Thomas Dagonnier - Sean Boran (FreeNAC Core Team)
 * @copyright			2006 FreeNAC
 * @license			http://www.gnu.org/copyleft/gpl.html   GNU Public License Version 2
 * @version			CVS: $Id:$
 * @link			http://www.freenac.net
 *
 */


# Php weirdness: change to script dir, then look for includes
chdir(dirname(__FILE__));
set_include_path("../:./");
require_once "bin/funcs.inc";               # Load settings & common functions
require_once "snmp_defs.inc";

define_syslog_variables();              # not used yet, but anyway..
openlog("snmp_import.php", LOG_PID, LOG_LOCAL5);

// Enable debugging to understand how the script works
  $debug_flag1=false;
  $debug_flag2=false;
  $debug_to_syslog=FALSE;

debug2("Checking for SNMP: " . SNMP_OID_OUTPUT_FULL); // we'll gte a number if PHP SNMP is working
snmp_set_oid_numeric_print(TRUE);
snmp_set_quick_print(TRUE);
snmp_set_enum_print(TRUE); 

// allow performance measurements
   $mtime = microtime();
   $mtime = explode(" ",$mtime);
   $mtime = $mtime[1] + $mtime[0];
   $starttime = $mtime;

db_connect();

// command line:
//   Look for: snmp_import.php -switch sw0503
//   otherwise scan all switches
// TODO switch instead of if, make sure there are 3 args!
$single = FALSE;
for ($i=0;$i<$argc;$i++) {
   if ($argv[$i]=='-switch') {   // even if user gives --switch we see -switch
	  $single = TRUE;
	  $singleswitch = mysql_real_escape_string($argv[$i+1]);
	};
};

if (!$single) {
        $switches =  mysql_fetch_all("SELECT * FROM switch");
	debug1("Scanning all switches in the Database");
} else {
        $switches =  mysql_fetch_all("SELECT * FROM switch WHERE name='$singleswitch'");
	debug1("Scanning one switch: $singleswitch");
};


if (is_array($switches)) {

	foreach ($switches as $switchrow) {
		$switch = $switchrow['ip'];
		$switchname = $switchrow['name'];
		$location = $switchrow['location'];
        	debug2("snmpwalk $switch,$switchname,$location for interfaces");

		$ifaces = walk_ports($switch,$snmp_ro);
  	    if (count($ifaces) > 0) {
		 foreach ($ifaces as $idx => $myiface) {
			if ($myiface['vmps'] && $myiface['vlan'] > 0) {
                		debug2("Vmps candidates vlan>0: $switchname interfaces " .$myiface['name'] .', vlan=' .$myiface['vlan'] );
				if (iface_exist($switch,$myiface['name'])) {
					$query = "UPDATE port SET default_vlan='".$myiface['vlan']."' WHERE ";
					$query .= "switch='$switch' AND name='".$myiface['name']."';";
				} else {
					$query = "INSERT INTO port(switch,name,default_vlan,location) VALUES (";
					$query .= "'$switch','".$myiface['name']."','".$myiface['vlan']."','$location');";

				};
				echo "$query\n";
			//        mysql_query($query) or die("unable to query");
				unset($query);
			};
		 };
        };
		unset($ifaces);
	};
};

  // measure performance
   $mtime = microtime();
   $mtime = explode(" ",$mtime);
   $mtime = $mtime[1] + $mtime[0];
   $endtime = $mtime;
   $totaltime = ($endtime - $starttime);
   debug1("Time taken= ".$totaltime." seconds\n");
   #logit("Time taken= ".$totaltime." seconds\n");


?>