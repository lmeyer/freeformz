function updateParent() {
	parent.updateIframeSize('freeformz_form',$('html').height());
}

$(document)
	.ready(updateParent)
	.click(updateParent);