function jQueryFunctionStack()
{
	this.stackArray = new Array();
	this.add = function (functionName)
	{
		if(this.add.arguments.length == 1)
		{
			this.stackArray.push(functionName+"();");
		}
		else if(this.add.arguments.length >= 2)
		{
			var List = "\"" + this.add.arguments[1] + "\"";
			for (var i = 2; i < this.add.arguments.length; ++i)
			{
				if(this.add.arguments[i] != "undefined")
				{
					List += ",\"" + this.add.arguments[i] + "\"";
				}
			}
			this.stackArray.push(functionName + "(" + List + ");");
		}	
	}
	this.getArray = function()
	{
		return this.stackArray;	
	}
}