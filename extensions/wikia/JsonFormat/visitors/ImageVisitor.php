<?php
/**
 * User: artur
 * Date: 31.05.13
 * Time: 17:31
 */

class ImageVisitor extends DOMNodeVisitorBase {

	/**
	 * @param DOMNode $currentNode
	 * @return bool
	 */
	public function canVisit(DOMNode $currentNode) {
		return DomHelper::isElement( $currentNode, 'a' )
			&& DomHelper::hasClass( $currentNode, 'image' )
			&& $currentNode->childNodes->length == 2
			&& DomHelper::isElement( $currentNode->childNodes->item(1), 'noscript' )
			&& DomHelper::isElement( $currentNode->childNodes->item(1)->childNodes->item(0), 'img' )
			;
	}

	/**
	 * @param DOMNode $currentNode
	 */
	public function visit(DOMNode $currentNode) {
		$src = $currentNode->childNodes->item(1)->childNodes->item(0)->getAttribute('src');
		$this->getJsonFormatTraversingState()->addChildToCurrentContainer( new JsonFormatImageNode($src) );
	}
}
