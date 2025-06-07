import json
import requests
from bs4 import BeautifulSoup


# Функция для парсинга страницы университета
def parse_university_page(url):
    try:
        # Загружаем страницу
        response = requests.get(url)
        response.raise_for_status()  # Проверка успешного запроса

        # Парсим HTML с BeautifulSoup
        soup = BeautifulSoup(response.content, 'html.parser')

        # Ищем все таблицы с классом 'napde'
        napde_tables = soup.find_all('table', class_='napde')
        napde_data = []
        for napde_table in napde_tables:
            rows = napde_table.find_all('tr')
            for row in rows[1:]:  # Пропускаем первую строку с заголовками
                columns = row.find_all('td')
                if len(columns) >= 4:  # Убедимся, что есть как минимум 4 колонки
                    # Извлекаем данные, игнорируя первую ячейку
                    description = columns[1].text.strip()
                    unit = columns[2].text.strip()
                    value = columns[3].text.strip()

                    # Разделяем данные на описание, значение и единицу измерения
                    napde_data.append({
                        'Описание': description,
                        'Значение': value,
                        'Единица измерения': unit
                    })

        # Ищем все таблицы с id 'analis_dop'
        analis_dop_tables = soup.find_all('table', id='analis_dop')
        analis_dop_data = []
        for analis_dop_table in analis_dop_tables:
            rows = analis_dop_table.find_all('tr')
            for row in rows[1:]:  # Пропускаем первую строку с заголовками
                columns = row.find_all('td')
                if len(columns) >= 4:  # Убедимся, что есть как минимум 4 колонки
                    # Извлекаем данные, игнорируя первую ячейку
                    description = columns[1].text.strip()
                    unit = columns[2].text.strip()
                    value = columns[3].text.strip()

                    # Разделяем данные на описание, значение и единицу измерения
                    analis_dop_data.append({
                        'Описание': description,
                        'Значение': value,
                        'Единица измерения': unit
                    })

        # Собираем все данные
        return {
            'napde_data': napde_data,
            'analis_dop_data': analis_dop_data
        }

    except requests.RequestException as e:
        print(f"Ошибка при загрузке страницы {url}: {e}")
        return None


# Загружаем файл с данными университетов
with open('universities_data_2015.json', 'r', encoding='utf-8') as f:
    universities = json.load(f)

# Список для хранения данных по всем университетам
all_universities_data = []

# Парсим данные для каждого университета
for university in universities:
    print(f"Парсим данные для: {university['name']}")

    # Парсим страницу университета
    university_data = parse_university_page(university['url'])

    # Если данные были успешно получены, добавляем их в список
    if university_data:
        all_universities_data.append({
            'name': university['name'],
            'url': university['url'],
            'coordinates': university['coordinates'],
            'region': university['region'],
            'napde_data': university_data['napde_data'],
            'analis_dop_data': university_data['analis_dop_data']
        })

# Сохраняем результаты в новый JSON файл
with open('universities_parsed_data2015.json', 'w', encoding='utf-8') as f:
    json.dump(all_universities_data, f, ensure_ascii=False, indent=4)

print("Парсинг завершен, данные сохранены в 'universities_parsed_data.json'.")
