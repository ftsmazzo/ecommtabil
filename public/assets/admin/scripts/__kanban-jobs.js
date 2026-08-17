// PEGAR O ELEMENTO ONDE ESTÃO OS CONTAINERS
var containerList = document.getElementById('kanban');
var containerSelectors = JSON.parse(containerList.dataset.containers);
var containers = containerSelectors.map(selector => document.getElementById(selector));

var drake = dragula(containers);

drake.on('drop', function(el, target, source, sibling) {

    // ATUALIZE O NÚMERO ENTRE PARÊNTESES EM TODAS AS LISTAS
    var lists = document.querySelectorAll('.task-list-items');
    var data = {};

    lists.forEach(function(list) {
        var itemCount = list.querySelectorAll('div.card').length;
        var taskHeader = list.parentElement.querySelector('.task-header');
        taskHeader.textContent = taskHeader.textContent.replace(/\(\d+\)/, '(' + itemCount + ')');

        // Coletar os dados da lista atualizada
        var listId = list.id;
        data[listId] = [];
        list.querySelectorAll('div.card').forEach(function(item) {
            var itemId = item.dataset.id;
            data[listId].push(itemId);
        });
    });

    console.log(data);

    // Enviar os dados atualizados para o servidor via AJAX
    var xhr = new XMLHttpRequest();
    xhr.open('POST', app.base + '/jobs/quadro/atualizar', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200) {
            console.log('Dados enviados com sucesso!');
        }
    };
    xhr.send(JSON.stringify(data));

});

// Adiciona classe CSS ao pegar o elemento para arrastar
drake.on('drag', function(el, source, handle, sibling) {
    el.style.transform = 'rotate(358deg)';
});

// Remove classe CSS após o arrasto terminar
drake.on('dragend', function(el) {
    el.style.transform = 'rotate(0deg)';
});
