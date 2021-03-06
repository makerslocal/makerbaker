<?php

class Ldap {
	
	private $ds;
	private $bound_dn;
	
	public function __construct($bind_dn = LdapInfo::bind_dn, $bind_pass = LdapInfo::bind_pass, $version = LdapInfo::version) {
		global $config;
		
		$this->ds = ldap_connect(LdapInfo::uri);
		ldap_set_option($this->ds, LDAP_OPT_PROTOCOL_VERSION, $version);
		if ( !$this->ds ) { die("LDAP failed to connect." . $config["error_text"]); }
		$r = ldap_bind($this->ds, $bind_dn, $bind_pass);
		if ( $r == false ) {
			ldap_get_option($this->ds,LDAP_OPT_ERROR_NUMBER,$err);
			if ( $err == 32 or $err == 49 ) { //invalid user or password
				throw new ErrorException("Bad login");
			}
			else { throw new ErrorException("Unknown error"); }
		}
		$this->bound_dn = $bind_dn;
	}
	
	public function search($filter, $base_dn = LdapInfo::base_dn) {
		$sr=ldap_search($this->ds, $base_dn, $filter);
		return(ldap_get_entries($this->ds, $sr));
	}
	
	public function getUserFromEmail($email) {
		$f = "(|(zimbraPrefMailForwardingAddress=" . $email . ")(mail=" . $email . ")(uid=" . $email . "))";
		$r = $this->search($f);
		if ( $r["count"] > 0 ) { return $r[0]; }
		return false;
	}
	public function getUserFromUid($uid) { return $this->getUserFromEmail($uid); }
	public function getUserFromDn($dn) { return $this->getUserFromUid(explode('=', explode(',', $dn)[0])[1]); } //kill me

	public function changePassword($uid, $pw) {

		$userPassword = "{SHA}" . base64_encode( pack( "H*", sha1( $pw ) ) );
		return $this->changeAttribute($uid, "userPassword", $userPassword);

	}

	public function changeAttribute($uid, $attribute, $value) {
		$dn = null;
		if ( $uid === null ) {
			$dn = $this->bound_dn;
		} else {
			$dn = "uid=" . $uid . "," . LdapInfo::base_dn;
		}

		//die($dn . "," . $attribute . "," . $value);

		$entry[$attribute] = array($value);
		$result = ldap_mod_replace($this->ds, $dn, $entry);

		if ( $result !== true ) {
			throw new ErrorException("Couldn't change password for some reason");
		}

		return $result;

	}
	
	function getGroup($cn) {
		//TODO: should we abstract this more? or just keep returning it raw?
	        $f = 'cn=' . $cn;
	        $r = $this->search($f, LdapInfo::group_base_dn);
	        if ( $r["count"] > 0 ) { return $r[0]; }
	        return false;
	}
	function getGroupMembers($cn) {
		$members = array();
		$group = $this->getGroup($cn);
		for ($i = 0; $i < $group["uniquemember"]["count"]; $i+=1) {
			//echo($group["uniquemember"][$i] . "\n");
			array_push($members, $this->search("uid=*", $group["uniquemember"][$i])[0]); //use the DN as the base DN for the search
		}
		//die(var_dump($members));
		return($members);
	}
	function getObjectClassMembers($class) {
		$members = array();
		$results = $this->search("objectclass=" . $class);
		for ($i = 0; $i < $results['count']; $i+=1) {
			array_push($members, $results[$i]);
		}
		return $members;
	}
}
