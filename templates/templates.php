<?php

function printTagBox($tag) { 
	?>
	<div 
		class			="tagElement" 
		style			="background-color: <?php print $tag['color'];?>;"
		title			="<?php print $tag['comment']?>" 
		data-ID		    ="<?php print $tag['ID'];?>"
		data-name		="<?php print $tag['name'];?>"
		data-justifies	="<?php print $tag['justifies'];?>"
		data-color		="<?php print $tag['color'];?>"
		data-showTag	="true"
		onclick			="tagFilterToggle(this)"
	>
		<?php print ($tag['justifies'] 
				? '<i class="fa fa-fw fa-check"    title="Dieses Tag setzt die Buchung auf erklärt."></i>' 
				: '<i class="fa fa-fw fa-question" title="Dieses Tag modifiziert den Erklärt-Status nicht."></i>');?>
		
		<div class='tagCaption'>
			<?php print $tag['name'];?>
			<br>
			<img src="/gfx/count.gif" alt="count" width="16"/>
			<span class='tagCount' style='width:1.5em;display:inline-block;'>ct</span>
			&Sigma;
			<span class='tagSum'>sum</span>
			
		</div>

		<div class="tagOnTheRight" title="Werden Buchungen mit diesem Tag angezeigt?">
			<i class="fa fa-eye tagVisible" aria-hidden="true"></i>
		</div>
		<div  onclick	="arguments[0].stopPropagation(); tagOpenEditor(this);"
			  title		="Bearbeiten"
			  class		="tagOnTheRight"
		>
			<i class="fa fa-pencil" aria-hidden="true"></i> 
		</div>
	</div>
	<?php 
}

function printRuleBox($rule, $db) {
	$allTags = $db->ruleGetTags($rule['ID']);
?>
	<div 
		class			="ruleElement" 
		title			="<?php print $rule['comment']?>" 
		data-ID		    ="<?php print $rule['ID'];?>"
		data-name		="<?php print $rule['name'];?>"
		data-filter		="<?php print $rule['filter'];?>"
		data-luxus		="<?php print $rule['luxus'];?>"
		data-recurrence	="<?php print $rule['recurrence'];?>"
		data-tagHas		="<?php print json_encode($allTags['has']);?>"
		data-tagCanHave	="<?php print json_encode($allTags['canHave']);?>"
		onclick			="ruleOpenEditor(this);"
	>
		<?php print $rule['name'];?>
		<br>
		<i class="fa fa-glass" aria-hidden="true">		<?php print $rule['luxus'];?></i>
		<i class="fa fa-refresh" aria-hidden="true">	<?php print $rule['recurrence'];?></i>
		<br>
		<?php 
			print implode(', ', $allTags['has']);
		?>
	</div>
<?php	
}
