<?php

class CargoSeismicFormat extends CargoDeferredFormat {

	public static function addFormat( &$formatClasses ) {
		$formatClasses['seismic'] = 'CargoSeismicFormat';
		return true;
	}

	function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'seismic';

		$linkAttrs = array(
			'href' => $ce->getFullURL( $queryParams ),
		);
		$text = Html::rawElement( 'a', $linkAttrs, 'View Seismic JSON' );

		return $text;
	}

	static function convertToSeismicDataStructure( $html ) {
		// First, change headings - Seismic can't handle <h1>, etc. tags.
		$html = preg_replace( '/\<h.\>/', '<p>', $html );
		$html = preg_replace( '/\<\/h.\>/', '</p>', $html );

		preg_match_all( '/(.*?)\<table.*?\>(.*?)\<\/table\>/mis', $html, $matches);

		if ( count( $matches[1] ) == 0 ) {
			$freeTextValues = [ $html ];
			$tableHTMLValues = [];
		} else {
			$freeTextValues = $matches[1];
			$tableHTMLValues = $matches[2];
		}

		$outputData = [];
		for ( $freeTextNum = 0; $freeTextNum < 10; $freeTextNum++ ) {
			$freeTextName = 'Text' . ( $freeTextNum + 1 );
			if ( count( $freeTextValues ) > $freeTextNum ) {
				$outputData[$freeTextName] = trim( $freeTextValues[$freeTextNum] );
			} else {
				$outputData[$freeTextName] = '';
			}
		}

		foreach ( $freeTextValues as $i => $freeText ) {
			$freeTextNum = $i + 1;
			$outputData['Text' . $freeTextNum] = trim( $freeText );
		}

		$lastTableTag = strrpos( $html, '</table>' );
		$lastFreeTextValue = trim( substr( $html, $lastTableTag + 8 ) );
		$outputData['Text' . ++$freeTextNum] = $lastFreeTextValue;

		$outputData['Tables'] = [];

		foreach ( $tableHTMLValues as $i => $tableHTML ) {
			$tableNum = $i + 1;
			$valuesArray = self::getValuesFromTableHTML( $tableHTML );
			foreach ( $valuesArray as $rowValues ) {
				$rowValues['TableNum'] = $tableNum;
				$outputData['Tables'][] = $rowValues;
			}
		}
		return $outputData;
	}

	static function getValuesFromTableHTML( $tableHTML ) {
		$tableValues = [];
		preg_match_all( '/\<tr.*?\>(.*?)\<\/tr\>/mis', $tableHTML, $matches);
		$rows = $matches[1];

		if (count( $rows ) == 0 ) {
			return array();
		}

		$rowValues = [];
		preg_match_all( '/\<th.*?\>(.*?)\<\/th\>/mis', $rows[0], $matches2);
		$thValues = $matches2[1];
		if ( count( $thValues ) > 0 ) {
			for ( $colNum = 0; $colNum < 10; $colNum++ ) {
				$colName = 'Col' . ( $colNum + 1 );
				if ( count( $thValues ) > $colNum ) {
					$rowValues[$colName] = trim( $thValues[$colNum] );
				} else {
					$rowValues[$colName] = '';
				}
			}
			$tableValues[] = $rowValues;
		}

		for ( $rowNum = count( $tableValues ); $rowNum < count( $rows ); $rowNum++ ) {
			$rowValues = [];
			preg_match_all( '/\<td.*?\>(.*?)\<\/td\>/mis', $rows[$rowNum], $matches3);
			$tdValues = $matches3[1];
			for ( $colNum = 0; $colNum < 10; $colNum++ ) {
				$colName = 'Col' . ( $colNum + 1 );
				if ( count( $tdValues ) > $colNum ) {
					$rowValues[$colName] = trim( $tdValues[$colNum] );
				} else {
					$rowValues[$colName] = '';
				}
			}
			$tableValues[] = $rowValues;
		}
		return $tableValues;
	}

	public static function displayData( $format, $sqlQueries, $req ) {
		if ( $format !== 'seismic' ) {
			return;
		}

		header( "Content-Type: application/json" );

		$allQueryResults = array();
		foreach ( $sqlQueries as $sqlQuery ) {
			$queryResults = $sqlQuery->run();
			$ce = new CargoExport();
			$queryResults = $ce->parseWikitextInQueryResults( $queryResults );

			// Turn "List" fields into arrays.
			foreach ( $sqlQuery->mFieldDescriptions as $alias => $fieldDescription ) {
				if ( $fieldDescription->mIsList ) {
					$delimiter = $fieldDescription->getDelimiter();
					for ( $i = 0; $i < count( $queryResults ); $i++ ) {
						$curValues = $queryResults[$i][$alias];
						if ( !is_array( $curValues ) ) {
							$curValues = explode( $delimiter, $curValues );
						}
						$seismicValues = [];
						foreach ( $curValues as $curValue ) {
							$seismicValues[] = self::convertToSeismicDataStructure( $curValue );
						}
						$queryResults[$i][$alias] = $seismicValues;
					}
				} else {
					for ( $i = 0; $i < count( $queryResults ); $i++ ) {
						$queryResults[$i][$alias] = self::convertToSeismicDataStructure( $queryResults[$i][$alias] );
					}
				}
			}

			$allQueryResults = array_merge( $allQueryResults, $queryResults );
		}

		print json_encode( $allQueryResults, JSON_PRETTY_PRINT );

		return false;
	}

}
