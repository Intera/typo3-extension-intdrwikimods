CREATE TABLE tx_intdrwikimods_subscriptions (

	uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
	pid int(11) unsigned DEFAULT '0' NOT NULL,
	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	
	keyword varchar(255) DEFAULT '' NOT NULL,
	fe_user int(11) unsigned DEFAULT '0' NOT NULL

	PRIMARY KEY (uid),
	KEY parent (pid),
);