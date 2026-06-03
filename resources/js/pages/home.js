import Chart from '../components/Chart.js';

Chart.setId('resultadoVenda').getData('/home/resultado-vendas').BAR().render();
Chart.setId('resultadoMarketing').getData('/home/resultado-marketing').PIE().render();