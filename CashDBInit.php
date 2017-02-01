<?php
class CashDBInit extends CashDBbasics {
	protected function initDatabase() {
		if ($this->tableExists('buchungen'))
			return;
		
		/*
	data-structure needs
		have another table that holds the tags
		each buchung can have 0 to many tags.
		each tag can be linked to 0 to many buchungen
		
		have another table that holds the rules.
		rules can apply 0 to many tags.
		rules can set luxus-factor.
		
		have another table that links the tags to the buchungen.
		the link will record which rule it created or whether it was created manually.
		
		need to store, but dont know where/how:
		if a buchung is once, recurring monthly, recurring yearly.
			store that via the tag into the rule?
			make this property a tag? -> could be queried from every report.
			default surely is ONCE. 
		if a buchung is justified via a rule or manually or not at all yet.
			could buchungen without any tag be unjustified?
			thus warrant manual inspection.
			which either assigns a tag manually (thus removes the unjustified status)
			or either creates a rule, which in turn adds a tag (says "tag: justified through rule 23")
			could be a problem with azon/pp buchungen.
			they can be easily given tags via rules, 
			but as long as they are not manually checked or found in the tracking-data they are still unjustified.
			add a tag called "unexplained" and assign that to every imported record?
			rules may or may not remove that tag when they apply.
		a comment-field to add free-text to any record. 
			e.g. a reminder for anonymous PayPal records to note what was actually paid for.
		a way to store tracking-data for nondescript buchungen
			paypal and amazon transactions should be inserted
			they automatically become justified when their VWZ matches anything.
		add import-date to buchungen?
			
		the justification comes from the rule. 
		 * the rule sets the recurrence.
		 * the rule strictly defines which buchung is affected by its filter.
		 * currently the justification comes from the tag.
		 * but a tag can be set by multiple rules.
		 * after a tag was set, it is not clear which rule applied it.
		 * so justification must be noted at the buchung.
		 * it could be a CSV of the rules which have caused it.
		 * that makes the buchungs status clear.
		 * justified? empty = no || not empty = yes
		 * and it leaves a trace to the rule which has set it.
	
	reporting needs
		want to plot recurring cost vs flexible cost
		want to plot different categories (group by: versicherung, transport, wohnung - add everything else to rest)
		want to plot by luxus (group by luxus)
		want to know which buchungen are not explained yet.
		how to sort records by time? need to turn buchungstag into epoch or Y-m-d during import?
		 * 
	#TODO RFE? track if buchungen are missing? 
		 * i.e. expect money coming in or going out.
		 * should i be alerted if that did not happen?
		 * gehalt has not come?
		 * versicherung hat nicht abgebucht?
		 * i think not. maybe later?
		*/
		
$sql =<<<EOF
CREATE TABLE `buchungen` (
	`ID`	INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	--we need an ID obviously.

	`Kontonummer`	TEXT,
	`Buchungstag`	TEXT NOT NULL,
	`Wertstellung`	TEXT,
	`AuftraggeberEmpfaenger`	TEXT NOT NULL,
	`Buchungstext`	TEXT,
	`VWZ1`	TEXT,
	`VWZ2`	TEXT,
	`VWZ3`	TEXT,
	`VWZ4`	TEXT,
	`VWZ5`	TEXT,
	`VWZ6`	TEXT,
	`VWZ7`	TEXT,
	`VWZ8`	TEXT,
	`VWZ9`	TEXT,
	`VWZ10`	TEXT,
	`VWZ11`	TEXT,
	`VWZ12`	TEXT,
	`VWZ13`	TEXT,
	`VWZ14`	TEXT,
	`Betrag`	REAL NOT NULL,
	`Kontostand`	REAL,
	`Waehrung`	INTEGER,

	'importdate'	TEXT, 
	--epoch-timestamp of when this record was read into the DB.

	'BuchungstagSortable' TEXT,
	--Buchungstag converted into YYYY-MM-DD for easy computational sorting

	'rawCSV'		TEXT,
	--the imported csv-record-line as it came from file to prevent duplicate imports.

	'luxus'			INTEGER		
	/*
	the luxus factor (in percent). 
	0	(N)		necessary 
		- will be cold, hungry, really sad, out of job without it)
	50	(C)		for unaccountable cash withdrawls 
		- cannot really know what was bought with that, but now it's gone.
	100	(L)		luxus 
		- life would be sad without it (replacing broken stuff, fancy clothing)
	200	(XL)	quite luxus (things that most others will buy too? 
		- kultur, cinema, useful gadgets) 
	300	(XXL)	very luxus (things that only the rich people can affort 
		- therme, useless gadgets, insanely expensive gifts)
	*/

	`recurrence`	INTEGER,
	--how many months does this buchung cover?
	--12 means that this buchung will be once in 12 months.
	--3  is every three monts, so 4 times per year.
	--0  means does not repeat at all

	'comment'		TEXT,
	--if the user has something to say.
		
	'justified'		INTEGER
	--
);
EOF;
		$this->runQuery($sql);
		d("Database table 'buchungen' initialized");
		
		
		
$sql =<<<EOF
CREATE TABLE `tags` (
--tags describe a buchung when assigned.
--tags are used to filter buchungen for reporting.
--tags can mark a buchung as 'justified'.
--justified means that the transaction is allowed and happened for a known reason.
	`ID`		INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	'name'		TEXT,
	'comment'	TEXT,
		--some free text that explains something about what this tag means
		--so we can later still understand the intention of it.
	'justifies' INTEGER,
		--applying this tag justifies the buchung
	'color'		TEXT
		--gives a nice touch to the label and all its buchungen
		--format: #121212 to be compatible with the colorpicker
);
EOF;
		$this->runQuery($sql);
		d("Database table 'tags' initialized");

		
		
$sql =<<<EOF
CREATE TABLE `buchungXtag` (
--a buchung can have 0 to many tags.
--a tag can be assigned 0 to many buchungen.
	`buchungID`	INTEGER NOT NULL,
	`tagID`	INTEGER NOT NULL,
	'origin' INTEGER,
		--who has applied this tag?
		--is a CSV list of rule-IDs.
		--0 means manually applied.

	UNIQUE(ruleID, tagID) ON CONFLICT IGNORE
		--there should be only one rule<->tag relationship.
		--if the relationship is set again, it should be ignored.

);
EOF;
		#TODO add symbol to tag for font-awesome!
		$this->runQuery($sql);
		d("Database table 'buchungXtag' initialized");

		
		
$sql =<<<EOF
CREATE TABLE `rules` (
--new records will be inspected by these rules.
--if they match, the buchung will be manipulated (tags added, luxus and recurrence changes)
	`ID`		INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	`name`		TEXT,
	'comment'	TEXT,
	'filter'	TEXT,
		--regex goes in here in format /regex/ so that it can be directly used in preg_something()
	'luxus'		INTEGER,
		--what to set matching buchungen to
	'amountLow'	REAL,
	'amountHigh'REAL,
		--OPTIONAL. what amount of money is to be expected by the buchung for the filter to apply
		--enter negative numbers for cash leaving and positive numbers for cash coming (gehalt etc)
	#TODO warn if the regex matches, but not the amount
	'expected
		--was this buchung expected? does applying this rule justify the transaction?
	'recurrence' INTEGER
		--for how many months is this payment? 
		/*
		NULL	no change
		0		one-time-payment
		1		valid for month, repeats monthly
		12		valid for 12 months, repeats yearly
		*/
);
EOF;
		$this->runQuery($sql);
		d("Database table 'rules' initialized");

		
		
$sql =<<<EOF
CREATE TABLE `ruleXtag` (
--these tags will be applied to the buchung if that rule matches.
--when a rule matches 0 to many tags may be applied
	`ruleID`	INTEGER NOT NULL,
	`tagID`		INTEGER NOT NULL,

);
EOF;
		$this->runQuery($sql);
		d("Database table 'ruleXtag' initialized");
		d("Database initialization complete.");
	}
	
}