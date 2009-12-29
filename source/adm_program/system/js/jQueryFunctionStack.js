function jQueryFunctionStack()
{
	this.stackArray = new Array();
	this.add = function (functionName)
	{
		this.stackArray.push(functionName);	
	}
	this.getArray = function()
	{
		return this.stackArray;	
	}
}