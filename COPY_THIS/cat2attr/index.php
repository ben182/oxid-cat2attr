<?php

error_reporting (E_ALL);
ini_set ('display_errors', 'On');

$sCatId = (isset($_GET['catId'])) ? $_GET['catId'] : FALSE;
$sAttrId = (isset($_GET['attrId'])) ? $_GET['attrId'] : FALSE;
$bConfirm = (isset($_GET['confirm'])) ? (bool) $_GET['confirm'] : FALSE;
$bDebug = (isset($_GET['debug'])) ? (bool) $_GET['debug'] : FALSE;
$bLog = (isset($_GET['log'])) ? (bool) $_GET['debug'] : TRUE;
$sToken = (isset($_GET['token'])) ? $_GET['token'] : FALSE;
$bCleanDb = (isset($_GET['cleanDb'])) ? (bool) $_GET['cleanDb'] : TRUE;
$iSeachMode = (isset($_GET['searchMode'])) ? $_GET['searchMode'] : 0;

if ($sCatId && $sAttrId) {

	require_once('inc/cat2attr.class.php');

	$cat2attr = new cat2attr(array(
		'BOOTSTRAP_PATH' => '../../bootstrap.php',
		'GIVEN_TOKEN' => $sToken,
	));

	$aAllArticles = $cat2attr->prepareAllArticlesAndCats($sCatId, $iSeachMode);

	if ($bDebug) {

		echo '<pre>';
		print_r($aAllArticles);
		echo '</pre>';
	}

}


if ($bConfirm) {
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>oxid-cat2attr by ben182</title>
	<link rel="stylesheet" href="libs/general/general.css">
</head>
<body>
	<div class="wrapper container">

		<?php
			echo '
				<table class="table table-striped table-bordered dataTable" cellspacing="0" width="100%">
					<thead>
						<tr>
							<th>ARTICLE_OXID</th>
							<th>ARTICLE_NAME</th> 
							<th>ARTICLE_ART_NR</th>
							<th>ARTICLE_CATEGORY_OXID</th>
							<th>ARTICLE_CATEGORY_TITLE</th>
						</tr>
					</thead>
					<tbody>
			';

			if (!empty($aAllArticles)) {

				foreach ($aAllArticles as $sArticleId => $aArticleData) {

					foreach ($aArticleData['categorys'] as $aCategory) {
						echo '
							<tr>
								<td>' . $sArticleId . '</td>
								<td>' . $aArticleData['data']['OXTITLE'] . '</td>
								<td>' . $aArticleData['data']['OXARTNUM'] . '</td>
								<td>' . $aCategory['OXID'] . '</td>
								<td>' . $aCategory['DETAILS']['OXTITLE'] . '</td>
							</tr>
						';
					}
					
				}

			}

			echo '
					</tbody>
				</table>
			';
		?>

		
		<button id="btn_confirm" class="ladda-button" data-color="green" data-style="contract">
			<i class="material-icons">done</i>
		</button>

		<!-- Modal -->
		<div class="modal fade" id="modal1" tabindex="-1" role="dialog" aria-labelledby="modal">
		  <div class="modal-dialog" role="document">
		    <div class="modal-content">
		      <div class="modal-header">
		        <h4 class="modal-title">Response</h4>
		      </div>
		      <div class="modal-body">
		        
				<p class="changed_articles"></p>
				<p class="errors"></p>
		      </div>
		      <div class="modal-footer">
		        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
		        <button type="button" class="btn btn-primary">Save changes</button>
		      </div>
		    </div>
		  </div>
		</div>
			

	</div>

	<script src="libs/general/general.js"></script>
</body>
</html>

<?php
}else{

	$cat2attr->insert(array(
		'CAT_ID' => $sCatId,
		'ATTR_ID' => $sAttrId,
		'LOG' => $bLog,
		'CLEAN_DB' => $bCleanDb,
		'SEARCH_MODE' => $iSeachMode,
	));

	echo $cat2attr->getResponse();
}
?>
