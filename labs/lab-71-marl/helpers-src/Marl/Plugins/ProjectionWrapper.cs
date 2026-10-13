namespace Marl.Plugins;
using System.Xml.Serialization;
public class ProjectionWrapper<T1, T2>
{
    [XmlElement("ProjectedProperty0")]
    public T2? ProjectedProperty0 { get; set; }
}
