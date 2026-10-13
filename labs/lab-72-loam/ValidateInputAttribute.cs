namespace Loam;

[AttributeUsage(AttributeTargets.Method | AttributeTargets.Class)]
public sealed class ValidateInputAttribute : Attribute
{
    public ValidateInputAttribute(bool validate) { }
}
