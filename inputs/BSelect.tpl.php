<select id="<?php echo $this->name; ?>" <?php if($this->multiple) echo 'multiple="multiple"'; ?> name="<?php echo $this->name.($this->multiple?"[]":""); ?>"
<?php
foreach($this->attributes as $name=>$value)
{
    echo " $name='$value'";
}
?> >
<?php
foreach($this->data as $item)
{
    $value = $item["value"];
    $text = $item["text"];
?>
    <option value="<?php echo $value; ?>" <?php echo (($this->multiple)?in_array($value,$this->value):($value==$this->value))?"selected":""; ?>><?php echo $text; ?></option>
<?php
}
?>
</select>
<script type="text/javascript">
KoolReport.widget.init(<?php echo json_encode($this->getResources()); ?>,function(){
    <?php echo $this->name; ?> = $('#<?php echo $this->name;?>');
    var name = <?php echo $this->name; ?>;
    name.multiselect(<?php echo json_encode($this->options); ?>);
    name.defaultValue = name.val();
    name.reset = function() {
        var values = name.val();
        name.multiselect('deselect', values);
        name.multiselect('select', name.defaultValue);
    }
    <?php $this->clientSideReady();?>
});
</script>