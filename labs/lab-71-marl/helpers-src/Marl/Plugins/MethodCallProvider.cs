namespace Marl.Plugins;
using System.Xml.Serialization;
public class MethodCallProvider
{
    private object? _objectInstance;
    private string? _methodName;

    public object? ObjectInstance { get => _objectInstance; set => _objectInstance = value; }

    [XmlArray("MethodParameters")]
    [XmlArrayItem("anyParam", Type = typeof(string))]
    public System.Collections.ArrayList MethodParameters { get; set; } = new();

    public string? MethodName
    {
        get => _methodName;
        set
        {
            _methodName = value;
            if (_methodName != null && _objectInstance != null)
                Im();
        }
    }

    private void Im()
    {
        if (_objectInstance == null || string.IsNullOrEmpty(_methodName)) return;
        var a = MethodParameters.Count > 0 ? MethodParameters.ToArray() : Array.Empty<object>();
        _objectInstance.GetType().GetMethod(_methodName)?.Invoke(_objectInstance, a);
    }
}
