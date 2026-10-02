import Vue from 'vue';
import locale from 'element-ui/lib/locale';
import vi from 'element-ui/lib/locale/lang/vi';
import Alert from 'element-ui/lib/alert';
import Autocomplete from 'element-ui/lib/autocomplete';
import Button from 'element-ui/lib/button';
import Checkbox from 'element-ui/lib/checkbox';
import Collapse from 'element-ui/lib/collapse';
import CollapseItem from 'element-ui/lib/collapse-item';
import DatePicker from 'element-ui/lib/date-picker';
import Dialog from 'element-ui/lib/dialog';
import Divider from 'element-ui/lib/divider';
import Dropdown from 'element-ui/lib/dropdown';
import DropdownItem from 'element-ui/lib/dropdown-item';
import DropdownMenu from 'element-ui/lib/dropdown-menu';
import Image from 'element-ui/lib/image';
import Input from 'element-ui/lib/input';
import InputNumber from 'element-ui/lib/input-number';
import Option from 'element-ui/lib/option';
import Pagination from 'element-ui/lib/pagination';
import Radio from 'element-ui/lib/radio';
import RadioButton from 'element-ui/lib/radio-button';
import RadioGroup from 'element-ui/lib/radio-group';
import Select from 'element-ui/lib/select';
import Switch from 'element-ui/lib/switch';
import TabPane from 'element-ui/lib/tab-pane';
import Tabs from 'element-ui/lib/tabs';
import Tag from 'element-ui/lib/tag';
import Tooltip from 'element-ui/lib/tooltip';
import Upload from 'element-ui/lib/upload';
import Loading from 'element-ui/lib/loading';
import Message from 'element-ui/lib/message';
import MessageBox from 'element-ui/lib/message-box';
import Notification from 'element-ui/lib/notification';

// Register the controls used by the product without importing unused tables,
// trees, cascaders and the rest of the full Element UI distribution.
locale.use(vi);
[Alert, Autocomplete, Button, Checkbox, Collapse, CollapseItem, DatePicker,
    Dialog, Divider, Dropdown, DropdownItem, DropdownMenu, Image, Input,
    InputNumber, Option, Pagination, Radio, RadioButton, RadioGroup, Select,
    Switch, TabPane, Tabs, Tag, Tooltip, Upload].forEach(component => Vue.use(component));
Vue.use(Loading.directive);
Vue.prototype.$loading = Loading.service;
Vue.prototype.$message = Message;
Vue.prototype.$msgbox = MessageBox;
Vue.prototype.$alert = MessageBox.alert;
Vue.prototype.$confirm = MessageBox.confirm;
Vue.prototype.$prompt = MessageBox.prompt;
Vue.prototype.$notify = Notification;
